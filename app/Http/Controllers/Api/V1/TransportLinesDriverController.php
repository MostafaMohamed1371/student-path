<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Concerns\AppliesApiSchoolScoping;
use App\Http\Controllers\Api\V1\Concerns\FormatsParentApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\Guardian;
use App\Models\School;
use App\Models\Student;
use App\Services\TransportLines\TransportDriverCardBuilder;
use App\Support\ParentContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransportLinesDriverController extends Controller
{
    use AppliesApiSchoolScoping;
    use FormatsParentApiResponse;

    public function __construct(
        private readonly TransportDriverCardBuilder $cardBuilder,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'school_id' => ['nullable', 'integer', 'exists:schools,id'],
            'student_id' => ['nullable', 'integer', 'exists:students,id'],
            'shift_period' => ['nullable', 'string', 'in:MORNING,EVENING'],
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

        $driversQuery = Driver::query()
            ->whereIn('school_id', $schoolIds)
            ->where('status', 'active')
            ->with(['user', 'bus'])
            ->orderBy('school_id')
            ->orderBy('id');

        if ($request->filled('shift_period')) {
            $driversQuery->where('shift_period', (string) $request->query('shift_period'));
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

        $routeBySchoolAndBus = $this->cardBuilder->latestRouteTitlesBySchoolAndBus($schoolIds, $drivers);

        $studentsBySchoolForDistance = ParentContext::representativeStudentsWithLocationBySchool(
            $request->user(),
            $schoolIds,
        );

        $cards = $drivers->map(function (Driver $driver) use ($reservedByDriver, $routeBySchoolAndBus, $schools, $request, $student, $studentsBySchoolForDistance, $queryLat, $queryLng): array {
            $school = $schools->get($driver->school_id);
            $studentForDistance = $student ?? ($studentsBySchoolForDistance[(int) $driver->school_id] ?? null);
            $distanceKm = $this->cardBuilder->resolveDistanceKmToSchool(
                $queryLat,
                $queryLng,
                $studentForDistance,
                $request->user(),
                $school instanceof School ? $school : null,
            );

            return $this->cardBuilder->buildCard($driver, $reservedByDriver, $routeBySchoolAndBus, $distanceKm);
        })->values()->all();

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

        $reserved = $this->cardBuilder->reservedCountsByDriverId(collect([$driver]));
        $routes = $this->cardBuilder->latestRouteTitlesBySchoolAndBus([(int) $driver->school_id], collect([$driver]));

        $card = $this->cardBuilder->buildCard($driver, $reserved, $routes, $distanceKm);

        return $this->parentSuccess([
            'driver' => $card,
        ]);
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
