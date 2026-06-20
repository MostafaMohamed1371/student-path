<?php

namespace App\Http\Controllers\Web;

use App\Enums\TripType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\ManagesDashboardScoping;
use App\Http\Controllers\Web\Concerns\ProvidesDashboardIraqLocationFilters;
use App\Http\Controllers\Web\Concerns\ProvidesDashboardSchoolDriverFilters;
use App\Models\Driver;
use App\Models\School;
use App\Models\Student;
use App\Models\TransportRoute;
use App\Models\TransportRouteStudent;
use App\Services\Routes\RouteAssignmentPlanner;
use App\Services\Trips\DriverShiftResolver;
use App\Services\Trips\StudentShiftFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DashboardRouteController extends Controller
{
    use ManagesDashboardScoping;
    use ProvidesDashboardIraqLocationFilters;
    use ProvidesDashboardSchoolDriverFilters;

    public function __construct(
        private readonly RouteAssignmentPlanner $routeAssignmentPlanner,
        private readonly StudentShiftFilter $studentShiftFilter,
        private readonly DriverShiftResolver $driverShiftResolver,
    ) {}

    public function assignedDrivers(Request $request): View
    {
        $filters = $this->dashboardReportFilterContext(
            $request,
            withShiftFilter: true,
        );

        if ($filters['effectiveSchoolId'] > 0) {
            $this->abortUnlessCanMutateSchoolRosterForSchool($filters['effectiveSchoolId']);
        }

        $query = TransportRoute::query()
            ->with(['driver.bus', 'school', 'routeStudents'])
            ->where('status', 'active')
            ->orderByRaw('CASE WHEN driver_id IS NULL THEN 0 ELSE 1 END')
            ->orderBy('school_id')
            ->orderBy('name');
        $this->applyDashboardReportFilters($query, $filters, 'roster_school');
        if ((int) $filters['filterDriverId'] > 0) {
            $this->applyDashboardReportFilters($query, $filters, 'route_driver');
        }
        $this->applyRosterShiftFilter($query, $filters);
        $locationFilters = $this->iraqLocationFilterContext($request);
        $this->applyTransportRouteLocationFilter($query, $locationFilters);

        $routes = $query->paginate($this->dashboardListPerPage())->withQueryString();

        $driverOptionsForRoute = [];
        foreach ($routes as $route) {
            $driverOptionsForRoute[(int) $route->id] = $this->driversForRouteForm(
                (int) $route->school_id,
                (string) $route->trip_type,
                (int) $route->id,
            );
        }

        return view('dashboard.routes.assigned_drivers', array_merge($filters, $locationFilters, [
            'filterAction' => route('dashboard.assigned_drivers.index'),
            'routes' => $routes,
            'driverOptionsForRoute' => $driverOptionsForRoute,
        ]));
    }

    public function assignDriver(Request $request, TransportRoute $route): RedirectResponse
    {
        abort_unless($this->routeVisible($route), 404);
        $this->abortUnlessCanMutateSchoolRosterForSchool((int) $route->school_id);

        $validated = $request->validate([
            'driver_id' => ['required', 'integer', 'exists:drivers,id'],
        ]);

        $schoolId = (int) $route->school_id;
        $tripType = (string) $route->trip_type;
        $driverId = (int) $validated['driver_id'];

        $this->assertDriverMatchesTripType($driverId, $schoolId, $tripType);
        $this->assertDriverHasNoRouteForTripType($driverId, $tripType, (int) $route->id);

        $driver = Driver::query()->with('bus')->findOrFail($driverId);

        $route->update(['driver_id' => $driverId]);
        $route = $route->fresh() ?? $route;

        $this->routeAssignmentPlanner->syncDriverMetaFromRoute($driver, $route);
        $matchResult = $this->routeAssignmentPlanner->assignStudentsAlongRoute($route);

        $message = $matchResult['assigned'] > 0
            ? __('dashboard.route_driver_assigned_with_students', ['count' => $matchResult['assigned']])
            : __('dashboard.route_driver_assigned_success');

        return redirect()
            ->route('dashboard.assigned_drivers.index', $this->indexQueryParams($schoolId, $tripType, $driverId))
            ->with('success', $message);
    }

    public function index(Request $request): View
    {
        $filters = $this->dashboardReportFilterContext(
            $request,
            withShiftFilter: true,
        );

        if ($filters['effectiveSchoolId'] > 0) {
            $this->abortUnlessCanMutateSchoolRosterForSchool($filters['effectiveSchoolId']);
        }

        $query = TransportRoute::query()
            ->with(['driver.bus', 'school', 'routeStudents'])
            ->where('status', 'active')
            ->orderBy('name');
        $this->applyDashboardReportFilters($query, $filters, 'roster_school');
        if ((int) $filters['filterDriverId'] > 0) {
            $this->applyDashboardReportFilters($query, $filters, 'route_driver');
        }
        $this->applyRosterShiftFilter($query, $filters);
        $locationFilters = $this->iraqLocationFilterContext($request);
        $this->applyTransportRouteLocationFilter($query, $locationFilters);

        $routes = $query->paginate($this->dashboardListPerPage())->withQueryString();

        return view('dashboard.routes.index', array_merge($filters, $locationFilters, [
            'filterAction' => route('dashboard.routes.index'),
            'routes' => $routes,
        ]));
    }

    public function create(): View
    {
        $this->abortUnlessCanMutateSchoolRoster();
        $schools = $this->schoolsForRosterForm();
        $tripTypes = collect(TripType::cases())->map(fn (TripType $t): string => $t->value)->all();
        $locationForm = $this->iraqLocationFormContext(
            (int) old('district_id', 0),
            (int) old('area_id', 0),
            (int) old('neighborhood_id', 0),
        );

        return view('dashboard.routes.create', [
            'schools' => $schools,
            'tripTypes' => $tripTypes,
            ...$locationForm,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->abortUnlessCanMutateSchoolRoster();
        $validated = $this->enforceRosterSchoolIdForStaff($request->validate($this->routeRules()));
        $schoolId = (int) $validated['school_id'];
        $this->abortUnlessCanMutateSchoolRosterForSchool($schoolId);
        $school = School::query()->find($schoolId);
        $locationPayload = $this->resolveIraqLocationPayload($validated);
        $validated = $locationPayload['attributes'];
        $validated['shift_period'] = $this->driverShiftResolver->fromTripType($validated['trip_type']);
        $validated['driver_id'] = null;
        $validated['name'] = trim((string) ($validated['name'] ?? '')) !== ''
            ? trim((string) $validated['name'])
            : $this->defaultRouteNameForTripType($validated['trip_type'], $school);

        $route = TransportRoute::query()->create($validated);
        $this->syncModelNeighborhoods($route, $locationPayload['neighborhood_ids']);

        return redirect()
            ->route('dashboard.routes.index', $this->indexQueryParams($schoolId, $validated['trip_type']))
            ->with('success', __('dashboard.route_created_assign_driver_hint'))
            ->with('highlight_route_id', $route->id);
    }

    public function edit(TransportRoute $route): View
    {
        abort_unless($this->routeVisible($route), 404);
        $this->abortUnlessCanMutateSchoolRoster();
        $route->load(['driver', 'school', 'neighborhoods']);

        $locationForm = $this->iraqLocationFormContext(
            (int) old('district_id', $route->district_id ?? 0),
            (int) old('area_id', $route->area_id ?? 0),
            (int) old('neighborhood_id', $route->neighborhoods->first()?->id ?? 0),
        );

        return view('dashboard.routes.edit', [
            'route' => $route,
            'schools' => $this->schoolsForRosterForm(),
            'tripTypes' => collect(TripType::cases())->map(fn (TripType $t): string => $t->value)->all(),
            ...$locationForm,
        ]);
    }

    public function update(Request $request, TransportRoute $route): RedirectResponse
    {
        abort_unless($this->routeVisible($route), 404);
        $this->abortUnlessCanMutateSchoolRoster();
        $validated = $this->enforceRosterSchoolIdForStaff($request->validate($this->routeRules(true)));
        $schoolId = (int) ($validated['school_id'] ?? $route->school_id);
        $this->abortUnlessCanMutateSchoolRosterForSchool($schoolId);
        $tripType = (string) ($validated['trip_type'] ?? $route->trip_type);
        $locationPayload = $this->resolveIraqLocationPayload($validated);
        $validated = $locationPayload['attributes'];
        $validated['shift_period'] = $this->driverShiftResolver->fromTripType($tripType);
        unset($validated['driver_id']);

        if (trim((string) ($validated['name'] ?? '')) === '') {
            $school = School::query()->find($schoolId);
            $validated['name'] = $this->defaultRouteNameForTripType($tripType, $school);
        }

        $route->update($validated);
        $this->syncModelNeighborhoods($route, $locationPayload['neighborhood_ids']);

        if ($route->driver_id) {
            $driver = Driver::query()->find((int) $route->driver_id);
            if ($driver) {
                $this->routeAssignmentPlanner->syncDriverMetaFromRoute($driver, $route->fresh() ?? $route);
            }
        }

        return redirect()
            ->route('dashboard.routes.index', $this->indexQueryParams($schoolId, $tripType, (int) ($route->driver_id ?? 0)))
            ->with('success', __('dashboard.route_updated'));
    }

    public function destroy(TransportRoute $route): RedirectResponse
    {
        abort_unless($this->routeVisible($route), 404);
        $this->abortUnlessCanMutateSchoolRosterForSchool((int) $route->school_id);

        $schoolId = (int) $route->school_id;
        $tripType = (string) $route->trip_type;
        $route->delete();

        return redirect()
            ->route('dashboard.routes.index', $this->indexQueryParams($schoolId, $tripType))
            ->with('success', __('dashboard.route_deleted'));
    }

    public function formOptions(Request $request): JsonResponse
    {
        $this->abortUnlessCanMutateSchoolRoster();

        $validated = $request->validate([
            'school_id' => ['required', 'integer', 'exists:schools,id'],
            'trip_type' => ['required', 'string', 'max:32'],
            'except_route_id' => ['nullable', 'integer', 'exists:transport_routes,id'],
        ]);

        $schoolId = (int) $validated['school_id'];
        $this->abortUnlessCanMutateSchoolRosterForSchool($schoolId);

        $tripType = $validated['trip_type'];
        $exceptRouteId = isset($validated['except_route_id']) ? (int) $validated['except_route_id'] : null;

        $routeDrivers = TransportRoute::query()
            ->with('driver.bus')
            ->where('school_id', $schoolId)
            ->where('trip_type', $tripType)
            ->where('status', 'active')
            ->orderBy('name')
            ->get()
            ->filter(fn (TransportRoute $route): bool => $route->driver !== null)
            ->map(fn (TransportRoute $route): array => $this->routeDriverOptionRow($route))
            ->values()
            ->all();

        return response()->json([
            'drivers' => $this->driversForRouteForm($schoolId, $tripType, $exceptRouteId)
                ->map(fn (Driver $d): array => $this->driverOptionRow($d))
                ->values()
                ->all(),
            'route_drivers' => $routeDrivers,
        ]);
    }

    public function assignRouteMatching(Request $request): RedirectResponse
    {
        $this->abortUnlessCanMutateSchoolRoster();

        $validated = $request->validate([
            'school_id' => ['required', 'integer', 'exists:schools,id'],
            'trip_type' => ['required', 'string', 'max:32'],
            'driver_id' => ['required', 'integer', 'exists:drivers,id'],
        ]);

        $schoolId = (int) $validated['school_id'];
        $driverId = (int) $validated['driver_id'];
        $this->abortUnlessCanMutateSchoolRosterForSchool($schoolId);

        $route = TransportRoute::query()
            ->with(['school', 'driver.bus'])
            ->where('school_id', $schoolId)
            ->where('trip_type', $validated['trip_type'])
            ->where('driver_id', $driverId)
            ->where('status', 'active')
            ->firstOrFail();

        $result = $this->routeAssignmentPlanner->assignStudentsAlongRoute($route);

        return redirect()
            ->route('dashboard.routes.index', $this->indexQueryParams($schoolId, $validated['trip_type'], $driverId))
            ->with('success', __('dashboard.route_driver_assigned', [
                'assigned' => $result['assigned'],
                'skipped_location' => $result['skipped_no_location'],
                'skipped_corridor' => $result['skipped_off_corridor'],
                'skipped_capacity' => $result['skipped_no_capacity'],
            ]));
    }

    public function autoAssign(Request $request): RedirectResponse
    {
        $this->abortUnlessCanMutateSchoolRoster();

        $validated = $request->validate([
            'school_id' => ['required', 'integer', 'exists:schools,id'],
            'trip_type' => ['required', 'string', 'max:32'],
            'driver_id' => ['nullable', 'integer', 'exists:drivers,id'],
            'clear_existing' => ['nullable', 'boolean'],
        ]);

        $schoolId = (int) $validated['school_id'];
        $driverId = (int) ($validated['driver_id'] ?? 0);
        $this->abortUnlessCanMutateSchoolRosterForSchool($schoolId);

        if ($driverId > 0) {
            $route = TransportRoute::query()
                ->with(['school', 'driver.bus'])
                ->where('school_id', $schoolId)
                ->where('trip_type', $validated['trip_type'])
                ->where('driver_id', $driverId)
                ->where('status', 'active')
                ->firstOrFail();

            if ($request->boolean('clear_existing')) {
                $route->routeStudents()->delete();
            }

            $single = $this->routeAssignmentPlanner->assignStudentsAlongRoute($route);
            $result = [
                'assigned' => $single['assigned'],
                'skipped_no_location' => $single['skipped_no_location'],
                'skipped_off_corridor' => $single['skipped_off_corridor'],
                'skipped_no_capacity' => $single['skipped_no_capacity'],
            ];
        } else {
            $result = $this->routeAssignmentPlanner->autoAssignForSchoolTripType(
                $schoolId,
                $validated['trip_type'],
                $request->boolean('clear_existing'),
            );
        }

        return redirect()
            ->route('dashboard.routes.index', $this->indexQueryParams($schoolId, $validated['trip_type'], $driverId))
            ->with('success', __('dashboard.route_auto_assigned', [
                'assigned' => $result['assigned'],
                'skipped_location' => $result['skipped_no_location'],
                'skipped_corridor' => $result['skipped_off_corridor'],
                'skipped_capacity' => $result['skipped_no_capacity'],
            ]));
    }

    public function assignStudent(Request $request): RedirectResponse
    {
        $this->abortUnlessCanMutateSchoolRoster();

        $validated = $request->validate([
            'school_id' => ['required', 'integer', 'exists:schools,id'],
            'trip_type' => ['required', 'string', 'max:32'],
            'driver_id' => ['required', 'integer', 'exists:drivers,id'],
            'student_id' => ['required', 'integer', 'exists:students,id'],
        ]);

        $schoolId = (int) $validated['school_id'];
        $this->abortUnlessCanMutateSchoolRosterForSchool($schoolId);

        $this->routeAssignmentPlanner->assignStudentToDriverRoute(
            $schoolId,
            (int) $validated['driver_id'],
            $validated['trip_type'],
            (int) $validated['student_id'],
        );

        $driverId = (int) $validated['driver_id'];

        return redirect()
            ->route('dashboard.routes.index', $this->indexQueryParams($schoolId, $validated['trip_type'], $driverId))
            ->with('success', __('dashboard.route_student_assigned'));
    }

    public function removeStudent(TransportRouteStudent $routeStudent): RedirectResponse
    {
        $this->abortUnlessCanMutateSchoolRoster();

        $routeStudent->load('transportRoute');
        $route = $routeStudent->transportRoute;
        abort_unless($route instanceof TransportRoute, 404);
        abort_unless($this->routeVisible($route), 404);

        $schoolId = (int) $route->school_id;
        $tripType = (string) $route->trip_type;

        $routeStudent->delete();

        if ($route->routeStudents()->count() === 0) {
            $route->delete();
        }

        $driverId = (int) ($route->driver_id ?? 0);

        return redirect()
            ->route('dashboard.routes.index', $this->indexQueryParams($schoolId, $tripType, $driverId))
            ->with('success', __('dashboard.route_student_removed'));
    }

    /**
     * @return array<string, int|string>
     */
    private function indexQueryParams(int $schoolId, string $tripType, int $driverId = 0): array
    {
        $params = [
            'school_id' => $schoolId,
            'trip_type' => $tripType,
        ];

        if ($driverId > 0) {
            $params['driver_id'] = $driverId;
        }

        return $params;
    }

    /**
     * @return array{id: int, label: string, route_id: int}
     */
    private function routeDriverOptionRow(TransportRoute $route): array
    {
        $driver = $route->driver;
        $busNo = $driver?->bus?->number;
        $name = trim(($driver?->first_name ?? '').' '.($driver?->last_name ?? ''));

        return [
            'id' => (int) $route->driver_id,
            'route_id' => (int) $route->id,
            'label' => $name
                .($busNo ? ' — '.__('dashboard.bus_number').': '.$busNo : '')
                .' · '.$route->name,
        ];
    }

    private function routeRules(bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return [
            'school_id' => [$required, 'integer', 'exists:schools,id'],
            'trip_type' => [$required, 'string', 'max:32'],
            'name' => ['nullable', 'string', 'max:255'],
            'start_address' => [$required, 'string', 'max:500'],
            'start_latitude' => [$required, 'numeric', 'between:-90,90'],
            'start_longitude' => [$required, 'numeric', 'between:-180,180'],
            'status' => [$required, 'in:active,inactive'],
            'monthly_subscription_price' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
            'district_id' => ['nullable', 'integer', 'exists:districts,id'],
            'area_id' => ['nullable', 'integer', 'exists:areas,id'],
            'neighborhood_id' => ['nullable', 'integer', 'exists:neighborhoods,id'],
            'neighborhood_ids' => ['nullable', 'array'],
            'neighborhood_ids.*' => ['integer', 'exists:neighborhoods,id'],
        ];
    }

    private function routeVisible(TransportRoute $route): bool
    {
        if ((bool) auth()->user()?->is_admin) {
            return true;
        }

        $sid = auth()->user()?->scopingSchoolId();

        return $sid !== null && (int) $route->school_id === (int) $sid;
    }

    private function assertDriverMatchesTripType(int $driverId, int $schoolId, string $tripType): void
    {
        $exists = Driver::query()
            ->whereKey($driverId)
            ->where('school_id', $schoolId)
            ->where('status', 'active')
            ->whereHas('bus')
            ->tap(fn (Builder $q) => $this->applyDriverTripTypeFilter($q, $tripType))
            ->exists();

        abort_unless($exists, 422);
    }

    /**
     * @param  Builder<Driver>  $query
     */
    private function applyDriverTripTypeFilter(Builder $query, string $tripType): void
    {
        $shift = $this->driverShiftResolver->fromTripType($tripType);
        if ($shift === null) {
            return;
        }

        $query->where(function (Builder $q) use ($shift): void {
            $q->whereNull('shift_period')
                ->orWhere('shift_period', $shift)
                ->orWhere('shift_period', 'BOTH');
        });
    }

    /**
     * @return Collection<int, Driver>
     */
    /**
     * @return Collection<int, Driver>
     */
    private function driversForRouteForm(int $schoolId, string $tripType, ?int $exceptRouteId = null): Collection
    {
        if ($schoolId <= 0 || trim($tripType) === '') {
            return collect();
        }

        $tripType = trim($tripType);

        return Driver::query()
            ->with('bus')
            ->where('school_id', $schoolId)
            ->where('status', 'active')
            ->whereHas('bus')
            ->tap(fn (Builder $q) => $this->applyDriverTripTypeFilter($q, $tripType))
            ->whereDoesntHave('transportRoutes', function (Builder $q) use ($tripType, $exceptRouteId): void {
                $q->where('trip_type', $tripType);
                if ($exceptRouteId !== null) {
                    $q->where('id', '!=', $exceptRouteId);
                }
            })
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();
    }

    private function assertDriverHasNoRouteForTripType(int $driverId, string $tripType, ?int $exceptRouteId = null): void
    {
        $query = TransportRoute::query()
            ->where('driver_id', $driverId)
            ->where('trip_type', trim($tripType));

        if ($exceptRouteId !== null) {
            $query->where('id', '!=', $exceptRouteId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'driver_id' => [__('dashboard.route_driver_trip_type_exists')],
            ]);
        }
    }

    /**
     * @return array{id: int, label: string}
     */
    private function driverOptionRow(Driver $driver): array
    {
        $busNo = $driver->bus?->number;

        return [
            'id' => (int) $driver->id,
            'label' => trim(($driver->first_name ?? '').' '.($driver->last_name ?? ''))
                .($busNo ? ' — '.__('dashboard.bus_number').': '.$busNo : '')
                .' (#'.(int) $driver->id.')',
        ];
    }

    private function defaultRouteName(Driver $driver, string $tripType): string
    {
        $name = trim(($driver->first_name ?? '').' '.($driver->last_name ?? ''));

        return trim($name) !== '' ? "Route — {$name} ({$tripType})" : "Route ({$tripType})";
    }

    private function defaultRouteNameForTripType(string $tripType, ?School $school = null): string
    {
        $schoolName = trim((string) ($school?->name_en ?? ''));

        return $schoolName !== '' ? "Route — {$schoolName} ({$tripType})" : "Route ({$tripType})";
    }
}
