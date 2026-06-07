<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Concerns\AppliesApiSchoolScoping;
use App\Http\Controllers\Api\V1\Concerns\FormatsParentApiResponse;
use App\Http\Controllers\Controller;
use App\Enums\TripType;
use App\Models\Driver;
use App\Models\Guardian;
use App\Models\School;
use App\Models\Student;
use App\Models\TransportRoute;
use App\Models\TripHistory;
use App\Models\User;
use App\Services\Drivers\DriverServiceAreaStudentMatcher;
use App\Services\Routes\RouteAssignmentPlanner;
use App\Services\TransportLines\TransportDriverCardBuilder;
use App\Services\Trips\DriverShiftResolver;
use App\Support\ParentContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransportLinesDriverController extends Controller
{
    use AppliesApiSchoolScoping;
    use FormatsParentApiResponse;

    public function __construct(
        private readonly TransportDriverCardBuilder $cardBuilder,
        private readonly RouteAssignmentPlanner $routeAssignmentPlanner,
        private readonly DriverServiceAreaStudentMatcher $serviceAreaStudentMatcher,
        private readonly DriverShiftResolver $driverShiftResolver,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'school_id' => ['nullable', 'integer', 'exists:schools,id'],
            'student_id' => ['nullable', 'integer', 'exists:students,id'],
            'shift_period' => ['nullable', 'string', 'in:MORNING,EVENING'],
            'trip_type' => ['nullable', 'string', 'max:32'],
            'matches_route_only' => ['nullable', 'boolean'],
            'has_transport_route' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:120'],
            'min_monthly_price' => ['nullable', 'integer', 'min:0'],
            'max_monthly_price' => ['nullable', 'integer', 'min:0'],
            'has_monthly_price' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $student = null;
        if ($request->filled('student_id')) {
            $student = Student::query()->findOrFail((int) $request->query('student_id'));
            if (! ParentContext::ownsStudent($request->user(), (int) $student->id)) {
                return $this->parentError('forbidden', null, 403);
            }
            if ($resp = $this->ensureApiTargetsOwnSchoolOrAdmin($request->user(), (int) $student->school_id)) {
                return $resp;
            }
        }

        $resolved = $this->resolveTargetSchoolIds($request);
        if ($resolved instanceof JsonResponse) {
            return $resolved;
        }

        /** @var list<int> $schoolIds */
        $schoolIds = $resolved;

        foreach ($schoolIds as $sid) {
            if ($resp = $this->ensureApiTargetsOwnSchoolOrAdmin($request->user(), $sid)) {
                return $resp;
            }
        }

        $schools = School::query()->whereIn('id', $schoolIds)->get()->keyBy('id');

        $tripType = trim((string) $request->query('trip_type', ''));
        if ($tripType !== '' && ! in_array($tripType, array_map(static fn (TripType $t): string => $t->value, TripType::cases()), true)) {
            return $this->parentError('Invalid trip_type.', null, 422);
        }

        $isParent = ! $this->isApiAdmin($request->user());
        $filterStudent = $student ?? $this->resolveSingleOwnedStudentForParent($request->user());

        $driversQuery = Driver::query()
            ->whereIn('school_id', $schoolIds)
            ->where('status', 'active')
            ->with(['user', 'bus'])
            ->orderBy('school_id')
            ->orderBy('id');

        if ($request->filled('shift_period')) {
            $driversQuery->where(function (Builder $q) use ($request): void {
                $shift = (string) $request->query('shift_period');
                $q->where('shift_period', $shift)
                    ->orWhere('shift_period', 'BOTH')
                    ->orWhereNull('shift_period');
            });
        } elseif ($isParent && $filterStudent !== null) {
            $this->applyDriverShiftFilterForStudent($driversQuery, $filterStudent);
        }

        $requireTransportRoute = $request->boolean('has_transport_route')
            || $tripType !== ''
            || $isParent;

        if ($requireTransportRoute) {
            $driversQuery->where(function (Builder $q) use ($tripType, $filterStudent): void {
                $q->whereHas('transportRoutes', function ($routeQuery) use ($tripType, $filterStudent): void {
                    $routeQuery->where('status', 'active')
                        ->whereNotNull('start_latitude')
                        ->whereNotNull('start_longitude');
                    if ($tripType !== '') {
                        $routeQuery->where('trip_type', $tripType);

                        return;
                    }
                    if ($filterStudent !== null) {
                        $shiftTripTypes = $this->tripTypesForStudentShift($filterStudent);
                        if ($shiftTripTypes !== []) {
                            $routeQuery->whereIn('trip_type', $shiftTripTypes);
                        }
                    }
                })->orWhereHas('serviceAreas.neighborhoods', function (Builder $neighborhoodQuery): void {
                    $neighborhoodQuery->whereNotNull('latitude')->whereNotNull('longitude');
                })->orWhereExists(function ($sub) use ($tripType, $filterStudent): void {
                    $sub->selectRaw('1')
                        ->from((new TripHistory)->getTable())
                        ->whereColumn('trip_histories.driver_id', 'drivers.id')
                        ->whereNotNull('route_title')
                        ->where('route_title', '!=', '');

                    if ($tripType !== '') {
                        $sub->where('trip_type', $tripType);

                        return;
                    }

                    if ($filterStudent !== null) {
                        $shiftTripTypes = $this->tripTypesForStudentShift($filterStudent);
                        if ($shiftTripTypes !== []) {
                            $sub->whereIn('trip_type', $shiftTripTypes);
                        }
                    }
                });
            });
        }

        $applyRouteCorridorFilter = $filterStudent !== null
            && (
                ($isParent && ! $request->has('matches_route_only'))
                || $request->boolean('matches_route_only')
            );

        if ($applyRouteCorridorFilter) {
            $matchingDriverIds = $this->matchingDriverIdsForStudent(
                $filterStudent,
                $schoolIds,
                $tripType !== '' ? $tripType : null,
                $request->user(),
            );
            if ($matchingDriverIds === []) {
                $driversQuery->whereRaw('1 = 0');
            } else {
                $driversQuery->whereIn('id', $matchingDriverIds);
            }
        }

        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $driversQuery->where(function ($q) use ($search): void {
                $q->where('first_name', 'like', '%'.$search.'%')
                    ->orWhere('father_name', 'like', '%'.$search.'%')
                    ->orWhere('grandfather_name', 'like', '%'.$search.'%')
                    ->orWhere('last_name', 'like', '%'.$search.'%')
                    ->orWhere('primary_phone', 'like', '%'.$search.'%')
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', '%'.$search.'%'))
                    ->orWhereHas('bus', function ($b) use ($search): void {
                        $b->where('number', 'like', '%'.$search.'%')
                            ->orWhere('type', 'like', '%'.$search.'%')
                            ->orWhere('name', 'like', '%'.$search.'%');
                    });
            });
        }

        if ($request->filled('min_monthly_price')) {
            $driversQuery->where('monthly_subscription_price', '>=', (int) $request->query('min_monthly_price'));
        }
        if ($request->filled('max_monthly_price')) {
            $driversQuery->where('monthly_subscription_price', '<=', (int) $request->query('max_monthly_price'));
        }
        if ($request->has('has_monthly_price')) {
            $hasMonthlyPrice = filter_var($request->query('has_monthly_price'), FILTER_VALIDATE_BOOLEAN);
            if ($hasMonthlyPrice) {
                $driversQuery->whereNotNull('monthly_subscription_price');
            } else {
                $driversQuery->whereNull('monthly_subscription_price');
            }
        }

        $rows = $driversQuery->paginate(min(100, max(1, (int) $request->query('per_page', 20))));
        $drivers = collect($rows->items());

        $queryLat = $request->filled('latitude') ? (float) $request->query('latitude') : null;
        $queryLng = $request->filled('longitude') ? (float) $request->query('longitude') : null;

        $reservedByDriver = $this->cardBuilder->reservedCountsByDriverId($drivers);

        $routeBySchoolAndBus = $this->cardBuilder->latestTripRouteMetaForDrivers(
            $schoolIds,
            $drivers,
            $tripType !== '' ? $tripType : null,
        );
        $transportRoutesByDriver = $this->cardBuilder->activeTransportRoutesByDriverId(
            $drivers,
            $tripType !== '' ? $tripType : null,
        );

        $studentsBySchoolForDistance = ParentContext::representativeStudentsWithLocationBySchool(
            $request->user(),
            $schoolIds,
        );

        $studentForCards = $student ?? $filterStudent;

        $cards = $drivers->map(function (Driver $driver) use ($reservedByDriver, $routeBySchoolAndBus, $transportRoutesByDriver, $schools, $request, $studentForCards, $studentsBySchoolForDistance, $queryLat, $queryLng): array {
            $school = $schools->get($driver->school_id);
            $studentForDistance = $studentForCards ?? ($studentsBySchoolForDistance[(int) $driver->school_id] ?? null);
            $distanceKm = $this->cardBuilder->resolveDistanceKmToSchool(
                $queryLat,
                $queryLng,
                $studentForDistance,
                $request->user(),
                $school instanceof School ? $school : null,
            );

            $transportRoute = $transportRoutesByDriver->get($driver->id);

            return $this->cardBuilder->buildCard(
                $driver,
                $reservedByDriver,
                $routeBySchoolAndBus,
                $distanceKm,
                $transportRoute instanceof TransportRoute ? $transportRoute : null,
                $studentForDistance,
            );
        })->values()->all();

        if ($studentForCards !== null) {
            $cards = $this->sortDriverCardsByRouteMatch($cards);
        }

        return $this->parentSuccess([
            'schoolIds' => array_map(static fn (int $id): string => (string) $id, $schoolIds),
            'drivers' => $cards,
            'pagination' => [
                'current_page' => $rows->currentPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
                'last_page' => $rows->lastPage(),
            ],
        ]);
    }

    public function show(Request $request, Driver $driver): JsonResponse
    {
        if ($resp = $this->ensureApiTargetsOwnSchoolOrAdmin($request->user(), (int) $driver->school_id)) {
            return $resp;
        }

        if ($driver->status !== 'active') {
            return $this->parentError('Driver is not available.', null, 404);
        }

        $driver->load(['user', 'bus']);

        $request->validate([
            'student_id' => ['nullable', 'integer', 'exists:students,id'],
            'trip_type' => ['nullable', 'string', 'max:32'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $student = null;
        if ($request->filled('student_id')) {
            $student = Student::query()->findOrFail((int) $request->query('student_id'));
            if (! ParentContext::ownsStudent($request->user(), (int) $student->id)) {
                return $this->parentError('forbidden', null, 403);
            }
            if ($resp = $this->ensureApiTargetsOwnSchoolOrAdmin($request->user(), (int) $student->school_id)) {
                return $resp;
            }
        }

        $school = School::query()->find((int) $driver->school_id);
        $queryLat = $request->filled('latitude') ? (float) $request->query('latitude') : null;
        $queryLng = $request->filled('longitude') ? (float) $request->query('longitude') : null;
        $studentsBySchoolForDistance = ParentContext::representativeStudentsWithLocationBySchool(
            $request->user(),
            [(int) $driver->school_id],
        );
        $studentForDistance = $student ?? ($studentsBySchoolForDistance[(int) $driver->school_id] ?? null);
        $distanceKm = $this->cardBuilder->resolveDistanceKmToSchool(
            $queryLat,
            $queryLng,
            $studentForDistance,
            $request->user(),
            $school,
        );

        $tripType = trim((string) $request->query('trip_type', ''));
        if ($tripType !== '' && ! in_array($tripType, array_map(static fn (TripType $t): string => $t->value, TripType::cases()), true)) {
            return $this->parentError('Invalid trip_type.', null, 422);
        }

        $reserved = $this->cardBuilder->reservedCountsByDriverId(collect([$driver]));
        $tripRouteMetaByDriver = $this->cardBuilder->latestTripRouteMetaForDrivers(
            [(int) $driver->school_id],
            collect([$driver]),
            $tripType !== '' ? $tripType : null,
        );
        $transportRoutes = $this->cardBuilder->activeTransportRoutesByDriverId(
            collect([$driver]),
            $tripType !== '' ? $tripType : null,
        );
        $transportRoute = $transportRoutes->get($driver->id);

        $card = $this->cardBuilder->buildCard(
            $driver,
            $reserved,
            $tripRouteMetaByDriver,
            $distanceKm,
            $transportRoute instanceof TransportRoute ? $transportRoute : null,
            $studentForDistance,
        );

        return $this->parentSuccess([
            'driver' => $card,
        ]);
    }

    private function resolveSingleOwnedStudentForParent(User $user): ?Student
    {
        $owned = ParentContext::studentsFor($user);

        return $owned->count() === 1 ? $owned->first() : null;
    }

    /**
     * @param  Builder<Driver>  $query
     */
    private function applyDriverShiftFilterForStudent(Builder $query, Student $student): void
    {
        $studentShift = strtoupper(trim((string) ($student->shift_period ?? '')));
        if ($studentShift === '') {
            return;
        }

        $query->where(function (Builder $q) use ($studentShift): void {
            $q->whereNull('shift_period')
                ->orWhere('shift_period', 'BOTH')
                ->orWhere('shift_period', $studentShift);
        });
    }

    /**
     * @return list<string>
     */
    private function tripTypesForStudentShift(Student $student): array
    {
        $shift = strtoupper(trim((string) ($student->shift_period ?? '')));

        return match ($shift) {
            DriverShiftResolver::MORNING => [
                TripType::MORNING_PICKUP->value,
                TripType::MORNING_RETURN->value,
            ],
            DriverShiftResolver::EVENING => [
                TripType::EVENING_PICKUP->value,
                TripType::EVENING_RETURN->value,
            ],
            default => [],
        };
    }

    private function pickupMatchesRouteCorridor(Student $student, TransportRoute $route, User $user): bool
    {
        if ($this->routeAssignmentPlanner->studentMatchesRouteCorridor($student, $route)) {
            return true;
        }

        $parentLatLng = $this->cardBuilder->resolveViewerLatLng(null, null, $user);
        if ($parentLatLng === null) {
            return false;
        }

        return $this->routeAssignmentPlanner->pointMatchesRouteCorridor(
            $parentLatLng[0],
            $parentLatLng[1],
            $route,
        );
    }

    /**
     * @param  list<int>  $schoolIds
     * @return list<int>
     */
    private function matchingDriverIdsForStudent(
        Student $student,
        array $schoolIds,
        ?string $tripType,
        User $user,
    ): array {
        $school = School::query()->find((int) $student->school_id);
        $serviceAreaDriverIds = $school instanceof School
            ? $this->serviceAreaStudentMatcher->matchingDriverIdsForStudent($student, $school)
            : [];

        $query = TransportRoute::query()
            ->with('school')
            ->whereIn('school_id', $schoolIds)
            ->where('school_id', (int) $student->school_id)
            ->where('status', 'active')
            ->whereNotNull('start_latitude')
            ->whereNotNull('start_longitude');

        if ($tripType !== null && $tripType !== '') {
            $query->where('trip_type', $tripType);
        } else {
            $shiftTripTypes = $this->tripTypesForStudentShift($student);
            if ($shiftTripTypes !== []) {
                $query->whereIn('trip_type', $shiftTripTypes);
            }
        }

        $transportRouteDriverIds = $query->get()
            ->filter(fn (TransportRoute $route): bool => $this->pickupMatchesRouteCorridor($student, $route, $user))
            ->pluck('driver_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        return collect($serviceAreaDriverIds)
            ->merge($transportRouteDriverIds)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $cards
     * @return list<array<string, mixed>>
     */
    private function sortDriverCardsByRouteMatch(array $cards): array
    {
        usort($cards, function (array $a, array $b): int {
            $aMatch = ($a['matchesStudentRoute'] ?? false) === true ? 1 : 0;
            $bMatch = ($b['matchesStudentRoute'] ?? false) === true ? 1 : 0;
            if ($aMatch !== $bMatch) {
                return $bMatch <=> $aMatch;
            }

            $aDist = $a['distanceKm'] ?? null;
            $bDist = $b['distanceKm'] ?? null;
            if ($aDist !== null && $bDist !== null) {
                return $aDist <=> $bDist;
            }

            return 0;
        });

        return $cards;
    }

    /**
     * @return JsonResponse|list<int>
     */
    private function resolveTargetSchoolIds(Request $request): JsonResponse|array
    {
        $user = $request->user();

        if ($request->filled('school_id')) {
            return [(int) $request->query('school_id')];
        }

        if ($this->isApiAdmin($user)) {
            return $this->parentError('school_id is required', null, 422);
        }

        $studentSchoolIds = ParentContext::studentsFor($user)
            ->pluck('school_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->filter(fn (int $id): bool => $id > 0)
            ->values()
            ->all();

        if ($studentSchoolIds !== []) {
            return array_values(array_unique($studentSchoolIds));
        }

        $guardian = ParentContext::guardian($user);
        if ($guardian instanceof Guardian && $guardian->school_id) {
            return [(int) $guardian->school_id];
        }

        return $this->parentError(
            'Link your account to a guardian with a school, add students, or pass school_id.',
            null,
            403
        );
    }
}
