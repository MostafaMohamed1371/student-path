<?php

namespace App\Http\Controllers\Web;

use App\Enums\StudentTripStopStatus;
use App\Enums\TripType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\ManagesDashboardScoping;
use App\Http\Controllers\Web\Concerns\ProvidesDashboardIraqLocationFilters;
use App\Http\Controllers\Web\Concerns\ProvidesDashboardSchoolDriverFilters;
use App\Models\Driver;
use App\Models\School;
use App\Models\Student;
use App\Models\TripHistory;
use App\Models\TripHistoryStudent;
use App\Models\TransportRoute;
use App\Services\Drivers\DriverServiceAreaTripFormatter;
use App\Services\Push\TripTrackingAuthorization;
use App\Services\Routes\RouteAssignmentPlanner;
use App\Services\Trips\DriverShiftResolver;
use App\Services\Trips\DriverTripModuleService;
use App\Services\Trips\StudentShiftFilter;
use App\Services\Trips\TripStudentAvailability;
use App\Services\Trips\TripLocationTrackingService;
use App\Services\Trips\PickupReturnTripPairPlanner;
use App\Services\Trips\TripTransportRouteApplier;
use App\Support\Geo\Haversine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DashboardTripController extends Controller
{
    use ManagesDashboardScoping;
    use ProvidesDashboardIraqLocationFilters;
    use ProvidesDashboardSchoolDriverFilters;

    public function __construct(
        private readonly StudentShiftFilter $studentShiftFilter,
        private readonly TripStudentAvailability $tripStudentAvailability,
        private readonly TripTransportRouteApplier $tripTransportRouteApplier,
        private readonly RouteAssignmentPlanner $routeAssignmentPlanner,
        private readonly DriverServiceAreaTripFormatter $driverServiceAreaTripFormatter,
    ) {}

    public function index(Request $request): View
    {
        $filters = $this->dashboardReportFilterContext($request, withShiftFilter: true);

        $query = TripHistory::query()
            ->with(['school', 'driver'])
            ->orderByDesc('start_time');
        $this->applyDashboardReportFilters($query, $filters, 'trip_history');
        $this->applyTripHistoryShiftFilter($query, $filters);
        $locationFilters = $this->iraqLocationFilterContext($request);
        $this->applyTripLocationFilter($query, $locationFilters);

        $trips = $query->paginate($this->dashboardListPerPage())->withQueryString();

        return view('dashboard.trips.index', array_merge($filters, $locationFilters, [
            'filterAction' => route('dashboard.trips.index'),
            'trips' => $trips,
        ]));
    }

    public function show(
        TripHistory $trip,
        DriverTripModuleService $driverTripModule,
        TripLocationTrackingService $locationTracking,
        TripTrackingAuthorization $trackingAuth,
    ): View {
        abort_unless($this->tripHistoryVisible($trip), 404);
        $trip->load(['school', 'driver.bus', 'tripHistoryStudents.student']);

        $tripDetail = $driverTripModule->tripDetailPayload($trip);

        $user = auth()->user();
        $canTrackTrip = $user !== null && $trackingAuth->canSubscribe($user, $trip);
        $tripTrackingInitial = null;

        if ($canTrackTrip) {
            try {
                $tripTrackingInitial = $locationTracking->trackingPayloadForUser($user, $trip);
            } catch (\Throwable) {
                $tripTrackingInitial = null;
            }
        }

        $status = strtoupper((string) ($trip->status ?? ''));
        $tripIsLive = $trip->driver_started_at !== null
            && ! in_array($status, ['CANCELLED', 'COMPLETED'], true);

        $mapMarkers = $this->tripMapMarkers($trip);

        $broadcastConnection = (string) config('broadcasting.default', 'null');
        $pusherEnabled = $broadcastConnection === 'pusher'
            && filled(config('broadcasting.connections.pusher.key'));

        return view('dashboard.trips.show', [
            'trip' => $trip,
            'tripDetail' => $tripDetail,
            'canTrackTrip' => $canTrackTrip,
            'tripTrackingInitial' => $tripTrackingInitial,
            'tripIsLive' => $tripIsLive,
            'mapMarkers' => $mapMarkers,
            'pusherEnabled' => $pusherEnabled,
            'pusherKey' => config('broadcasting.connections.pusher.key'),
            'pusherCluster' => config('broadcasting.connections.pusher.cluster'),
            'locationBroadcastEvent' => (string) config('trips.location_broadcast_event', 'driver.location.updated'),
            'trackingPollUrl' => route('dashboard.trips.tracking', $trip),
        ]);
    }

    public function tracking(
        TripHistory $trip,
        TripLocationTrackingService $locationTracking,
        TripTrackingAuthorization $trackingAuth,
    ): JsonResponse {
        abort_unless($this->tripHistoryVisible($trip), 404);

        $user = auth()->user();
        if ($user === null || ! $trackingAuth->canSubscribe($user, $trip)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $trip->loadMissing(['school', 'driver.bus', 'tripHistoryStudents.student']);

        try {
            $data = $locationTracking->trackingPayloadForUser($user, $trip);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage() ?: 'Unable to load tracking.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => $data,
            'markers' => $this->tripMapMarkers($trip),
        ]);
    }

    /**
     * @return array{school: array<string, mixed>|null, students: list<array<string, mixed>>}
     */
    private function tripMapMarkers(TripHistory $trip): array
    {
        $school = null;
        if ($trip->school && $trip->school->latitude !== null && $trip->school->longitude !== null) {
            $school = [
                'latitude' => (float) $trip->school->latitude,
                'longitude' => (float) $trip->school->longitude,
                'label' => (string) ($trip->school->name_en ?: $trip->school->name_ar),
            ];
        }

        $students = [];
        foreach ($trip->tripHistoryStudents as $ths) {
            $student = $ths->student;
            if (! $student || $student->latitude === null || $student->longitude === null) {
                continue;
            }
            $students[] = [
                'id' => (int) $student->id,
                'name' => (string) $student->full_name,
                'latitude' => (float) $student->latitude,
                'longitude' => (float) $student->longitude,
                'status' => (string) $ths->status,
            ];
        }

        return [
            'school' => $school,
            'students' => $students,
        ];
    }

    public function create(): View|RedirectResponse
    {
        $this->abortUnlessCanMutateSchoolRoster();
        $schools = $this->schoolsForRosterForm();
        if ($schools->isEmpty()) {
            return $this->redirectToSchoolCreateForAdminsOrHomeForStaff('dashboard.create_school_first');
        }

        $tripTypes = collect(TripType::cases())->map(fn (TripType $t): string => $t->value)->all();
        $schoolId = (int) old('school_id', auth()->user()?->scopingSchoolId() ?? 0);
        $tripType = old('trip_type');
        $driverId = (int) old('driver_id', 0);
        $drivers = $schoolId > 0
            ? $this->driversForTripForm($schoolId, is_string($tripType) ? $tripType : null)
            : collect();

        return view('dashboard.trips.create', [
            'schools' => $schools,
            'drivers' => $drivers,
            'tripTypes' => $tripTypes,
            'formOptionsUrl' => route('dashboard.trips.form_options'),
            'driverAutoFillUrl' => route('dashboard.trips.driver_auto_fill'),
            'exceptTripId' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->abortUnlessCanMutateSchoolRoster();
        $validated = $this->enforceRosterSchoolIdForStaff($request->validate($this->rules(forCreate: true)));
        $this->abortUnlessCanMutateSchoolRosterForSchool((int) $validated['school_id']);
        $this->assertDriverBelongsToSchool(
            isset($validated['driver_id']) ? (int) $validated['driver_id'] : null,
            (int) $validated['school_id'],
        );

        $schoolId = (int) $validated['school_id'];
        $validated = $this->applyTripRouteFieldsFromRequest($request, $validated, $schoolId);
        $this->assertTripHasDriver($validated);
        $this->assertPickupTripHasEndTime($validated);
        $validated['students_count'] = 0;
        $validated['students_preview'] = [];

        $school = School::query()->findOrFail($schoolId);
        $pairPlanner = app(PickupReturnTripPairPlanner::class);

        $trip = DB::transaction(function () use ($validated, $request, $schoolId, $school, $pairPlanner): TripHistory {
            $trip = TripHistory::query()->create($validated);
            $this->createPairedReturnTripIfNeeded($trip, $validated, $request, $school, $schoolId, $pairPlanner);

            return $trip;
        });

        return redirect()
            ->route('dashboard.trips.assign_students', [
                'school_id' => $trip->school_id,
                'trip_id' => $trip->id,
            ])
            ->with('success', __('dashboard.trip_created_assign_students_next'));
    }

    public function edit(TripHistory $trip): View
    {
        abort_unless($this->tripHistoryVisible($trip), 404);
        $this->abortUnlessCanMutateSchoolRoster();
        $schools = $this->schoolsForRosterForm();
        $tripTypes = collect(TripType::cases())->map(fn (TripType $t): string => $t->value)->all();
        $schoolId = (int) old('school_id', $trip->school_id);
        $tripType = old('trip_type', $trip->trip_type);
        $drivers = $this->driversForTripForm($schoolId, is_string($tripType) ? $tripType : null);
        $tripStatus = strtoupper((string) old('status', $trip->status ?? ''));
        $selectableStatus = in_array($tripStatus, ['ACTIVE', 'PRESENT'], true) ? $tripStatus : 'PRESENT';

        return view('dashboard.trips.edit', [
            'trip' => $trip,
            'schools' => $schools,
            'drivers' => $drivers,
            'tripTypes' => $tripTypes,
            'selectableStatus' => $selectableStatus,
            'formOptionsUrl' => route('dashboard.trips.form_options'),
            'driverAutoFillUrl' => route('dashboard.trips.driver_auto_fill'),
            'exceptTripId' => (int) $trip->id,
        ]);
    }

    public function assignStudents(Request $request): View|RedirectResponse
    {
        $this->abortUnlessCanMutateSchoolRoster();
        $schools = $this->schoolsForRosterForm();
        if ($schools->isEmpty()) {
            return $this->redirectToSchoolCreateForAdminsOrHomeForStaff('dashboard.create_school_first');
        }

        $schoolId = (int) $request->query('school_id', old('school_id', auth()->user()?->scopingSchoolId() ?? $schools->first()?->id ?? 0));
        $driverId = (int) $request->query('driver_id', 0);
        $tripIds = $this->tripIdsFromAssignRequest($request);

        $drivers = $schoolId > 0 ? $this->driversForTripForm($schoolId, null) : collect();
        $trips = $schoolId > 0 ? $this->tripsForAssignForm($schoolId, $driverId > 0 ? $driverId : null) : collect();

        $selectedTrips = $this->resolveSelectedTripsForAssign($tripIds, $schoolId);
        if ($selectedTrips->isNotEmpty()) {
            $schoolId = (int) $selectedTrips->first()->school_id;
        }

        $selectedStudentIds = $this->assignedStudentIdsOnTrips($selectedTrips);
        $students = $selectedTrips->isNotEmpty()
            ? $this->studentsForMultipleTripsForm($schoolId, $selectedTrips, $selectedStudentIds)
            : collect();

        return view('dashboard.trips.assign_students', [
            'schools' => $schools,
            'drivers' => $drivers,
            'trips' => $trips,
            'selectedTrips' => $selectedTrips,
            'students' => $students,
            'selectedStudentIds' => $selectedStudentIds,
            'schoolId' => $schoolId,
            'driverId' => $driverId,
            'tripIds' => $selectedTrips->pluck('id')->map(fn ($id): int => (int) $id)->all(),
            'formOptionsUrl' => route('dashboard.trips.assign_students.form_options'),
        ]);
    }

    public function assignStudentsFormOptions(Request $request): JsonResponse
    {
        $this->abortUnlessCanMutateSchoolRoster();

        $validated = $request->validate([
            'school_id' => ['required', 'integer', 'exists:schools,id'],
            'driver_id' => ['nullable', 'integer', 'exists:drivers,id'],
            'trip_id' => ['nullable', 'integer', 'exists:trip_histories,id'],
            'trip_ids' => ['nullable', 'array'],
            'trip_ids.*' => ['integer', 'exists:trip_histories,id'],
            'include_student_ids' => ['nullable', 'array'],
            'include_student_ids.*' => ['integer'],
        ]);

        $schoolId = (int) $validated['school_id'];
        $this->abortUnlessCanMutateSchoolRosterForSchool($schoolId);

        $driverId = isset($validated['driver_id']) ? (int) $validated['driver_id'] : 0;
        if ($driverId > 0) {
            $this->assertDriverBelongsToSchool($driverId, $schoolId);
        }

        $tripIds = $this->tripIdsFromValidatedAssignInput($validated);
        $includeIds = array_map('intval', $validated['include_student_ids'] ?? []);

        $drivers = $this->driversForTripForm($schoolId, null)
            ->map(fn (Driver $d): array => [
                'id' => (int) $d->id,
                'label' => trim(($d->first_name ?? '').' '.($d->last_name ?? '')).' (#'.(int) $d->id.')',
            ])
            ->values()
            ->all();

        $trips = $this->tripsForAssignForm($schoolId, $driverId > 0 ? $driverId : null)
            ->map(fn (TripHistory $t): array => $this->tripOptionRow($t))
            ->values()
            ->all();

        $students = [];
        $selectedStudentIds = [];
        $selectedTripsPayload = [];
        $routeFilterActive = false;
        $corridorMaxKm = null;

        $selectedTrips = $this->resolveSelectedTripsForAssign($tripIds, $schoolId);
        if ($selectedTrips->isNotEmpty()) {
            $includeIds = array_values(array_unique(array_merge(
                $includeIds,
                $this->assignedStudentIdsOnTrips($selectedTrips),
            )));

            $students = $this->studentsForMultipleTripsForm($schoolId, $selectedTrips, $includeIds)
                ->map(fn (Student $s): array => $this->studentOptionRow($s))
                ->values()
                ->all();

            $selectedStudentIds = $this->assignedStudentIdsOnTrips($selectedTrips);

            foreach ($selectedTrips as $trip) {
                $tripType = is_string($trip->trip_type) && $trip->trip_type !== '' ? $trip->trip_type : null;
                $tripDriverId = $trip->driver_id !== null ? (int) $trip->driver_id : null;
                $activeRoute = $this->transportRouteForDriver($schoolId, $tripType, $tripDriverId);
                if ($activeRoute !== null) {
                    $routeFilterActive = true;
                    $corridorMaxKm = round((float) config('routes.corridor_max_meters', 3000) / 1000, 1);
                }

                $selectedTripsPayload[] = $this->tripSummaryPayload($trip);
            }
        }

        return response()->json([
            'drivers' => $drivers,
            'trips' => $trips,
            'selected_trips' => $selectedTripsPayload,
            'students' => $students,
            'selected_student_ids' => $selectedStudentIds,
            'route_filter_active' => $routeFilterActive,
            'corridor_max_km' => $corridorMaxKm,
        ]);
    }

    public function assignStudentsStore(Request $request): RedirectResponse
    {
        $this->abortUnlessCanMutateSchoolRoster();

        $validated = $request->validate([
            'trip_id' => ['nullable', 'integer', 'exists:trip_histories,id'],
            'trip_ids' => ['nullable', 'array', 'min:1'],
            'trip_ids.*' => ['integer', 'exists:trip_histories,id'],
            'student_ids' => ['nullable', 'array'],
            'student_ids.*' => ['integer', 'exists:students,id'],
        ]);

        $tripIds = $this->tripIdsFromValidatedAssignInput($validated);
        if ($tripIds === []) {
            throw ValidationException::withMessages([
                'trip_ids' => [__('dashboard.trip_assign_select_trip_placeholder')],
            ]);
        }

        $selectedTrips = TripHistory::query()
            ->whereIn('id', $tripIds)
            ->get()
            ->sortBy(fn (TripHistory $trip): int => array_search((int) $trip->id, $tripIds, true))
            ->values();

        if ($selectedTrips->count() !== count($tripIds)) {
            abort(404);
        }

        foreach ($selectedTrips as $trip) {
            abort_unless($this->tripHistoryVisible($trip), 404);
            $this->abortUnlessCanMutateSchoolRosterForSchool((int) $trip->school_id);

            if (in_array(strtoupper((string) $trip->status), ['CANCELLED', 'COMPLETED'], true)) {
                throw ValidationException::withMessages([
                    'trip_ids' => [__('dashboard.trip_assign_students_closed_trip')],
                ]);
            }
        }

        $schoolIds = $selectedTrips->pluck('school_id')->map(fn ($id): int => (int) $id)->unique()->values();
        if ($schoolIds->count() !== 1) {
            throw ValidationException::withMessages([
                'trip_ids' => [__('dashboard.trip_assign_trips_same_school')],
            ]);
        }

        $schoolId = (int) $schoolIds->first();
        $studentIds = array_values(array_unique(array_map(
            static fn ($v): int => (int) $v,
            $validated['student_ids'] ?? [],
        )));

        foreach ($selectedTrips as $trip) {
            $this->syncTripStudentsForSchool($trip, $studentIds, $schoolId, $tripIds);
        }

        $tripCount = $selectedTrips->count();
        $message = count($studentIds) > 0
            ? ($tripCount > 1
                ? __('dashboard.trip_students_assigned_multiple', ['count' => count($studentIds), 'trips' => $tripCount])
                : __('dashboard.trip_students_assigned', ['count' => count($studentIds)]))
            : ($tripCount > 1
                ? __('dashboard.trip_students_cleared_multiple', ['trips' => $tripCount])
                : __('dashboard.trip_students_cleared'));

        return redirect()
            ->route('dashboard.trips.assign_students', [
                'school_id' => $schoolId,
                'trip_ids' => $tripIds,
            ])
            ->with('success', $message);
    }

    public function formOptions(Request $request): JsonResponse
    {
        $this->abortUnlessCanMutateSchoolRoster();

        $validated = $request->validate([
            'school_id' => ['required', 'integer', 'exists:schools,id'],
            'trip_type' => ['nullable', 'string', 'max:32'],
            'driver_id' => ['nullable', 'integer', 'exists:drivers,id'],
            'include_student_ids' => ['nullable', 'array'],
            'include_student_ids.*' => ['integer'],
            'except_trip_id' => ['nullable', 'integer', 'exists:trip_histories,id'],
        ]);

        $schoolId = (int) $validated['school_id'];
        $this->abortUnlessCanMutateSchoolRosterForSchool($schoolId);
        $tripType = isset($validated['trip_type']) && trim((string) $validated['trip_type']) !== ''
            ? trim((string) $validated['trip_type'])
            : null;
        $driverId = isset($validated['driver_id']) ? (int) $validated['driver_id'] : null;
        if ($driverId !== null && $driverId > 0) {
            $this->assertDriverBelongsToSchool($driverId, $schoolId);
        }
        $includeIds = array_map('intval', $validated['include_student_ids'] ?? []);
        $exceptTripId = isset($validated['except_trip_id']) ? (int) $validated['except_trip_id'] : null;

        $drivers = $this->driversForTripForm($schoolId, $tripType);
        $routesByDriver = $this->tripTransportRouteApplier->routesByDriverId($drivers, $schoolId, $tripType);
        $activeRoute = $this->transportRouteForDriver($schoolId, $tripType, $driverId);

        return response()->json([
            'students' => $this->studentsForTripForm($schoolId, $tripType, $includeIds, $exceptTripId, $driverId)
                ->map(fn (Student $s): array => $this->studentOptionRow($s))
                ->values()
                ->all(),
            'drivers' => $drivers
                ->map(fn (Driver $d): array => $this->driverOptionRow(
                    $d,
                    $routesByDriver->get($d->id),
                ))
                ->values()
                ->all(),
            'route_filter_active' => $activeRoute !== null,
            'corridor_max_km' => $activeRoute !== null
                ? round((float) config('routes.corridor_max_meters', 3000) / 1000, 1)
                : null,
        ]);
    }

    public function driverAutoFill(Request $request): JsonResponse
    {
        $this->abortUnlessCanMutateSchoolRoster();

        $validated = $request->validate([
            'school_id' => ['required', 'integer', 'exists:schools,id'],
            'trip_type' => ['required', 'string', 'max:32'],
            'driver_id' => ['required', 'integer', 'exists:drivers,id'],
        ]);

        $schoolId = (int) $validated['school_id'];
        $driverId = (int) $validated['driver_id'];
        $tripType = trim($validated['trip_type']);

        $this->abortUnlessCanMutateSchoolRosterForSchool($schoolId);
        $this->assertDriverBelongsToSchool($driverId, $schoolId);

        $driver = Driver::query()->with('bus')->findOrFail($driverId);
        $route = $this->tripTransportRouteApplier->findRouteForTrip([
            'school_id' => $schoolId,
            'driver_id' => $driverId,
            'trip_type' => $tripType,
        ]);

        $payload = $this->driverOptionRow($driver, $route);
        $school = School::query()->find($schoolId);
        $serviceAreas = $this->driverServiceAreaTripFormatter->serviceAreasForDriver($driverId);

        return response()->json([
            'ok' => true,
            'has_route' => $route !== null,
            'has_service_areas' => $serviceAreas !== [],
            'service_areas' => $serviceAreas,
            'bus_number' => $payload['bus_number'],
            'route_title' => $payload['route_title'],
            'location' => $payload['location'],
            'distance_km' => $payload['distance_km'],
            'students_count' => $payload['students_count'],
            'start_address' => $payload['start_address'],
            'end_address' => $payload['end_address'] ?? ($school ? trim((string) ($school->address ?? '')) : null),
            'route_start_latitude' => $payload['route_start_latitude'],
            'route_start_longitude' => $payload['route_start_longitude'],
            'school_latitude' => $payload['school_latitude'] ?? ($school?->latitude !== null ? (float) $school->latitude : null),
            'school_longitude' => $payload['school_longitude'] ?? ($school?->longitude !== null ? (float) $school->longitude : null),
            'transport_route_id' => $payload['transport_route_id'],
        ]);
    }

    public function update(Request $request, TripHistory $trip): RedirectResponse
    {
        abort_unless($this->tripHistoryVisible($trip), 404);
        $this->abortUnlessCanMutateSchoolRoster();
        $validated = $this->enforceRosterSchoolIdForStaff($request->validate($this->rules(true)));
        $this->abortUnlessCanMutateSchoolRosterForSchool((int) ($validated['school_id'] ?? $trip->school_id));
        $this->assertDriverBelongsToSchool(
            isset($validated['driver_id']) ? (int) $validated['driver_id'] : null,
            (int) ($validated['school_id'] ?? $trip->school_id),
        );

        $validated = $this->applyTripRouteFieldsFromRequest(
            $request,
            $validated,
            (int) ($validated['school_id'] ?? $trip->school_id),
        );

        $trip->update($validated);

        return redirect()->route('dashboard.trips.index')
            ->with('success', __('dashboard.trip_updated'));
    }

    public function destroy(TripHistory $trip): RedirectResponse
    {
        abort_unless($this->tripHistoryVisible($trip), 404);
        $this->abortUnlessCanMutateSchoolRosterForSchool((int) $trip->school_id);
        $trip->delete();

        return redirect()->route('dashboard.trips.index')
            ->with('success', __('dashboard.trip_deleted'));
    }

    /**
     * Dashboard create/edit forms only allow ACTIVE and PRESENT (lifecycle statuses are set by the app).
     */
    private function rules(bool $partial = false, bool $forCreate = false): array
    {
        $required = $partial ? 'sometimes' : 'required';
        $statusValues = 'ACTIVE,PRESENT';

        $driverIdRules = $forCreate && ! $partial
            ? [$required, 'integer', 'exists:drivers,id']
            : ['nullable', 'integer', 'exists:drivers,id'];

        return [
            'school_id' => [$required, 'integer', 'exists:schools,id'],
            'driver_id' => $driverIdRules,
            'trip_type' => ['nullable', 'string', 'max:32'],
            'bus_number' => [$required, 'string', 'max:64'],
            'route_title' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'start_address' => ['nullable', 'string', 'max:255'],
            'start_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'start_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'students_count' => [$required, 'integer', 'min:0'],
            'distance_km' => [$required, 'numeric', 'min:0'],
            'start_time' => [$required, 'date'],
            'end_time' => [
                $forCreate && ! $partial ? 'required_if:trip_type,MORNING_PICKUP,EVENING_PICKUP' : 'nullable',
                'nullable',
                'date',
                'after_or_equal:start_time',
            ],
            'status' => [$required, 'in:'.$statusValues],
            'note' => ['nullable', 'string'],
            'student_ids' => [$partial ? 'sometimes' : 'nullable', 'array'],
            'student_ids.*' => ['integer', 'exists:students,id'],
            'driver_service_area_ids' => ['nullable', 'array'],
            'driver_service_area_ids.*' => ['integer', 'exists:driver_service_areas,id'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function applyTripRouteFieldsFromRequest(Request $request, array $validated, int $schoolId): array
    {
        unset($validated['student_ids'], $validated['driver_service_area_ids']);

        $serviceAreaIds = array_values(array_unique(array_filter(array_map(
            static fn ($v): int => (int) $v,
            $request->input('driver_service_area_ids', []),
        ), static fn (int $id): bool => $id > 0)));

        if ($serviceAreaIds !== [] && isset($validated['driver_id'])) {
            $this->assertDriverServiceAreasBelongToDriver($serviceAreaIds, (int) $validated['driver_id']);
            $combined = $this->driverServiceAreaTripFormatter->combineForTrip($serviceAreaIds, null);
            if (trim($combined['route_title']) !== '') {
                $validated['route_title'] = $combined['route_title'];
            }
        }

        $startLat = $this->nullableCoordinate($validated['start_latitude'] ?? null);
        $startLng = $this->nullableCoordinate($validated['start_longitude'] ?? null);

        if ($startLat === null || $startLng === null) {
            $route = $this->tripTransportRouteApplier->findRouteForTrip($validated);
            if (
                $route !== null
                && $route->start_latitude !== null
                && $route->start_longitude !== null
            ) {
                $startLat = (float) $route->start_latitude;
                $startLng = (float) $route->start_longitude;
                $validated['start_latitude'] = $startLat;
                $validated['start_longitude'] = $startLng;
                if (trim((string) ($validated['start_address'] ?? '')) === '') {
                    $startAddress = trim((string) ($route->start_address ?? ''));
                    $validated['start_address'] = $startAddress !== '' ? $startAddress : null;
                }
            }
        }

        $school = School::query()->find($schoolId);

        if ($startLat !== null && $startLng !== null) {
            $startAddress = trim((string) ($validated['start_address'] ?? ''));
            $endAddress = $school ? trim((string) ($school->address ?? '')) : '';
            $validated['location'] = $this->tripLocationLabel($startAddress, $endAddress);

            if (
                $school instanceof School
                && $school->latitude !== null
                && $school->longitude !== null
            ) {
                $validated['distance_km'] = round(
                    Haversine::metersBetween(
                        $startLat,
                        $startLng,
                        (float) $school->latitude,
                        (float) $school->longitude,
                    ) / 1000,
                    2,
                );
            }

            return $validated;
        }

        if (
            trim((string) ($validated['route_title'] ?? '')) === ''
            || trim((string) ($validated['location'] ?? '')) === ''
        ) {
            $validated = $this->tripTransportRouteApplier->applyRouteToTripAttributes(
                $validated,
                overwrite: $serviceAreaIds === [],
            );
        }

        $route = $this->tripTransportRouteApplier->findRouteForTrip($validated);
        if ($route !== null) {
            if (! isset($validated['start_latitude']) && $route->start_latitude !== null) {
                $validated['start_latitude'] = $route->start_latitude;
            }
            if (! isset($validated['start_longitude']) && $route->start_longitude !== null) {
                $validated['start_longitude'] = $route->start_longitude;
            }
            if (trim((string) ($validated['start_address'] ?? '')) === '' && trim((string) ($route->start_address ?? '')) !== '') {
                $validated['start_address'] = trim((string) $route->start_address);
            }
        }

        return $validated;
    }

    private function tripLocationLabel(string $start, string $end): ?string
    {
        if ($start === '' && $end === '') {
            return null;
        }

        if ($start === '') {
            return __('dashboard.trip_location_end_only', ['end' => $end]);
        }

        if ($end === '') {
            return __('dashboard.trip_location_start_only', ['start' => $start]);
        }

        return __('dashboard.trip_location_start_to_end', [
            'start' => $start,
            'end' => $end,
        ]);
    }

    private function nullableCoordinate(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    /**
     * @param  list<int>  $serviceAreaIds
     */
    private function assertDriverServiceAreasBelongToDriver(array $serviceAreaIds, int $driverId): void
    {
        if ($serviceAreaIds === []) {
            return;
        }

        $count = \App\Models\DriverServiceArea::query()
            ->where('driver_id', $driverId)
            ->whereIn('id', $serviceAreaIds)
            ->count();

        if ($count !== count($serviceAreaIds)) {
            throw ValidationException::withMessages([
                'driver_service_area_ids' => [__('dashboard.trip_driver_service_areas_invalid')],
            ]);
        }
    }

    /**
     * @param  list<int|string>  $studentIds
     * @param  int|list<int>|null  $exceptTripIds
     */
    private function syncTripStudentsForSchool(
        TripHistory $trip,
        array $studentIds,
        int $schoolId,
        int|array|null $exceptTripIds = null,
    ): void {
        $unique = array_values(array_unique(array_map(static fn ($v): int => (int) $v, $studentIds)));

        if ($unique === []) {
            $trip->tripHistoryStudents()->delete();
            $trip->update([
                'students_count' => 0,
                'students_preview' => [],
            ]);

            return;
        }

        $tripType = is_string($trip->trip_type) && $trip->trip_type !== '' ? $trip->trip_type : null;

        $except = $exceptTripIds ?? (int) $trip->id;
        $this->tripStudentAvailability->assertStudentsAvailableForTrip($unique, $schoolId, $except);

        $previouslyOnTrip = $trip->tripHistoryStudents()
            ->pluck('student_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        foreach ($unique as $sid) {
            $student = Student::query()->whereKey($sid)->where('school_id', $schoolId)->first();
            if (! $student) {
                throw ValidationException::withMessages([
                    'student_ids' => [__('dashboard.trip_students_school_mismatch')],
                ]);
            }
            if (! $this->studentShiftFilter->studentMatchesTripType($student, $tripType)) {
                throw ValidationException::withMessages([
                    'student_ids' => [__('dashboard.trip_students_shift_mismatch')],
                ]);
            }
        }

        $route = $this->transportRouteForDriver(
            $schoolId,
            $tripType,
            $trip->driver_id !== null ? (int) $trip->driver_id : null,
        );
        if ($route !== null) {
            foreach ($unique as $sid) {
                if (in_array($sid, $previouslyOnTrip, true)) {
                    continue;
                }
                $student = Student::query()->find($sid);
                if ($student && ! $this->routeAssignmentPlanner->studentEligibleForDriverRoute($student, $route)) {
                    throw ValidationException::withMessages([
                        'student_ids' => [__('dashboard.trip_students_off_route')],
                    ]);
                }
            }
        }

        $trip->tripHistoryStudents()->delete();

        $preview = [];
        foreach ($unique as $i => $sid) {
            TripHistoryStudent::query()->create([
                'trip_history_id' => $trip->id,
                'student_id' => $sid,
                'sort_order' => $i,
                'status' => StudentTripStopStatus::IDLE->value,
            ]);
            $s = Student::query()->find($sid);
            $preview[] = [
                'id' => (string) $sid,
                'name' => (string) ($s?->full_name ?? ''),
            ];
        }

        $trip->update([
            'students_count' => count($preview),
            'students_preview' => $preview,
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function assertTripHasDriver(array $validated): void
    {
        $driverId = (int) ($validated['driver_id'] ?? 0);
        if ($driverId <= 0) {
            throw ValidationException::withMessages([
                'driver_id' => [__('dashboard.trip_driver_required')],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function assertPickupTripHasEndTime(array $validated): void
    {
        $tripType = trim((string) ($validated['trip_type'] ?? ''));
        if (! TripType::isPickup($tripType)) {
            return;
        }

        if (filled($validated['end_time'] ?? null)) {
            return;
        }

        throw ValidationException::withMessages([
            'end_time' => [__('dashboard.trip_pickup_end_time_required')],
        ]);
    }

    /**
     * @param  array<string, mixed>  $pickupValidated
     */
    private function createPairedReturnTripIfNeeded(
        TripHistory $pickupTrip,
        array $pickupValidated,
        Request $request,
        School $school,
        int $schoolId,
        PickupReturnTripPairPlanner $pairPlanner,
    ): void {
        $returnAttributes = $pairPlanner->returnTripAttributesFromPickup($pickupValidated, $school);
        if ($returnAttributes === null) {
            return;
        }

        $returnTripType = (string) $returnAttributes['trip_type'];
        if ($pairPlanner->returnTripExistsForPickup($pickupTrip, $returnTripType)) {
            return;
        }

        // Return trip path/times are planned from school dismissal → driver pickup start.
        TripHistory::query()->create($returnAttributes);
    }

    /**
     * @return list<int>
     */
    private function tripIdsFromAssignRequest(Request $request): array
    {
        $tripIds = array_map('intval', (array) $request->query('trip_ids', []));
        $tripIds = array_values(array_unique(array_filter($tripIds, static fn (int $id): bool => $id > 0)));

        if ($tripIds !== []) {
            return $tripIds;
        }

        $tripId = (int) $request->query('trip_id', 0);

        return $tripId > 0 ? [$tripId] : [];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return list<int>
     */
    private function tripIdsFromValidatedAssignInput(array $validated): array
    {
        $tripIds = array_map('intval', $validated['trip_ids'] ?? []);
        $tripIds = array_values(array_unique(array_filter($tripIds, static fn (int $id): bool => $id > 0)));

        if ($tripIds !== []) {
            return $tripIds;
        }

        $tripId = (int) ($validated['trip_id'] ?? 0);

        return $tripId > 0 ? [$tripId] : [];
    }

    /**
     * @param  list<int>  $tripIds
     * @return Collection<int, TripHistory>
     */
    private function resolveSelectedTripsForAssign(array $tripIds, int $schoolId): Collection
    {
        if ($tripIds === []) {
            return collect();
        }

        return TripHistory::query()
            ->with(['driver', 'school'])
            ->whereIn('id', $tripIds)
            ->get()
            ->filter(fn (TripHistory $trip): bool => $this->tripHistoryVisible($trip)
                && ($schoolId <= 0 || (int) $trip->school_id === $schoolId))
            ->sortBy(fn (TripHistory $trip): int => array_search((int) $trip->id, $tripIds, true))
            ->values();
    }

    /**
     * @param  Collection<int, TripHistory>  $trips
     * @return list<int>
     */
    private function assignedStudentIdsOnTrips(Collection $trips): array
    {
        if ($trips->isEmpty()) {
            return [];
        }

        $sets = $trips->map(
            fn (TripHistory $trip): array => $trip->tripHistoryStudents()
                ->pluck('student_id')
                ->map(fn ($id): int => (int) $id)
                ->all(),
        )->all();

        return array_values(array_intersect(...$sets));
    }

    /**
     * @param  Collection<int, TripHistory>  $trips
     * @param  list<int>  $includeStudentIds
     * @return Collection<int, Student>
     */
    private function studentsForMultipleTripsForm(int $schoolId, Collection $trips, array $includeStudentIds = []): Collection
    {
        if ($trips->isEmpty()) {
            return collect();
        }

        $exceptTripIds = $trips->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $rows = null;

        foreach ($trips as $trip) {
            $onTrip = $trip->tripHistoryStudents()
                ->pluck('student_id')
                ->map(fn ($id): int => (int) $id)
                ->all();
            $include = array_values(array_unique(array_merge($includeStudentIds, $onTrip)));

            $forTrip = $this->studentsForTripForm(
                $schoolId,
                is_string($trip->trip_type) ? $trip->trip_type : null,
                $include,
                $exceptTripIds,
                $trip->driver_id !== null ? (int) $trip->driver_id : null,
            );

            if ($rows === null) {
                $rows = $forTrip;
            } else {
                $allowedIds = $forTrip->pluck('id')->flip();
                $rows = $rows->filter(fn (Student $student): bool => $allowedIds->has($student->id))->values();
            }
        }

        return $rows ?? collect();
    }

    /**
     * @return array{
     *     id: int,
     *     trip_type: string|null,
     *     driver_id: int|null,
     *     route_title: string|null,
     *     start_time: string|null,
     *     students_count: int
     * }
     */
    private function tripSummaryPayload(TripHistory $trip): array
    {
        return [
            'id' => (int) $trip->id,
            'trip_type' => $trip->trip_type,
            'driver_id' => $trip->driver_id,
            'route_title' => $trip->route_title,
            'start_time' => $trip->start_time?->format('Y-m-d H:i'),
            'students_count' => (int) $trip->students_count,
        ];
    }

    /**
     * @return Collection<int, TripHistory>
     */
    private function tripsForAssignForm(int $schoolId, ?int $driverId = null): Collection
    {
        if ($schoolId <= 0) {
            return collect();
        }

        $query = TripHistory::query()
            ->with('driver')
            ->where('school_id', $schoolId)
            ->whereNotIn('status', ['CANCELLED', 'COMPLETED'])
            ->orderByDesc('start_time')
            ->orderByDesc('id');

        if ($driverId !== null && $driverId > 0) {
            $query->where('driver_id', $driverId);
        }

        return $query->limit(200)->get();
    }

    /**
     * @return array{id: int, label: string, trip_type: string|null, driver_id: int|null}
     */
    private function tripOptionRow(TripHistory $trip): array
    {
        $trip->loadMissing('driver');
        $start = $trip->start_time instanceof \Illuminate\Support\Carbon
            ? $trip->start_time->format('Y-m-d H:i')
            : (string) $trip->start_time;
        $driverName = trim(($trip->driver?->first_name ?? '').' '.($trip->driver?->last_name ?? ''));
        $title = trim((string) ($trip->route_title ?? ''));
        $label = '#'.$trip->id;
        if ($title !== '') {
            $label .= ' — '.$title;
        }
        if ($start !== '') {
            $label .= ' — '.$start;
        }
        if ($driverName !== '') {
            $label .= ' — '.$driverName;
        }

        return [
            'id' => (int) $trip->id,
            'label' => $label,
            'trip_type' => is_string($trip->trip_type) ? $trip->trip_type : null,
            'driver_id' => $trip->driver_id !== null ? (int) $trip->driver_id : null,
        ];
    }

    private function tripHistoryVisible(TripHistory $trip): bool
    {
        if ((bool) auth()->user()?->is_admin) {
            return true;
        }
        $id = auth()->user()?->scopingSchoolId();
        if ($id === null) {
            return false;
        }

        return (int) $trip->school_id === (int) $id;
    }

    private function assertDriverBelongsToSchool(?int $driverId, int $schoolId): void
    {
        if ($driverId === null || $driverId <= 0) {
            return;
        }

        abort_unless(
            Driver::query()->whereKey($driverId)->where('school_id', $schoolId)->exists(),
            403,
        );
    }

    /**
     * @param  list<int>  $includeStudentIds
     * @return Collection<int, Student>
     */
    private function studentsForTripForm(
        int $schoolId,
        ?string $tripType,
        array $includeStudentIds = [],
        int|array|null $exceptTripIds = null,
        ?int $driverId = null,
    ): Collection {
        if ($schoolId <= 0) {
            return collect();
        }

        $bookedIds = $this->tripStudentAvailability->studentIdsOnActiveTrips($schoolId, $exceptTripIds);

        $query = Student::query()
            ->where('school_id', $schoolId)
            ->where('status', 'active')
            ->orderBy('full_name');

        if ($bookedIds !== []) {
            $query->whereNotIn('id', $bookedIds);
        }

        $this->studentShiftFilter->applyToStudentQuery($query, $tripType);

        $rows = $query->get();

        if ($includeStudentIds !== []) {
            $existing = $rows->pluck('id')->map(fn ($id): int => (int) $id)->all();
            $missing = array_values(array_diff($includeStudentIds, $existing));
            if ($missing !== []) {
                $extra = Student::query()
                    ->where('school_id', $schoolId)
                    ->whereIn('id', $missing)
                    ->orderBy('full_name')
                    ->get();
                $rows = $rows->merge($extra)->sortBy('full_name', SORT_NATURAL | SORT_FLAG_CASE)->values();
            }
        }

        $route = $this->transportRouteForDriver($schoolId, $tripType, $driverId);
        if ($route !== null) {
            $rows = $this->routeAssignmentPlanner->filterStudentsForDriverRoute($rows, $route);

            if ($includeStudentIds !== []) {
                $present = $rows->pluck('id')->map(fn ($id): int => (int) $id)->all();
                $missingSelected = array_values(array_diff($includeStudentIds, $present));
                if ($missingSelected !== []) {
                    $extra = Student::query()
                        ->where('school_id', $schoolId)
                        ->whereIn('id', $missingSelected)
                        ->orderBy('full_name')
                        ->get();
                    $rows = $rows->merge($extra)->sortBy('full_name', SORT_NATURAL | SORT_FLAG_CASE)->values();
                }
            }
        }

        return $rows;
    }

    private function transportRouteForDriver(int $schoolId, ?string $tripType, ?int $driverId): ?TransportRoute
    {
        if ($schoolId <= 0 || $driverId === null || $driverId <= 0) {
            return null;
        }

        $tripType = trim((string) ($tripType ?? ''));
        if ($tripType === '') {
            return null;
        }

        return $this->tripTransportRouteApplier->findRouteForTrip([
            'school_id' => $schoolId,
            'driver_id' => $driverId,
            'trip_type' => $tripType,
        ]);
    }

    /**
     * @return Collection<int, Driver>
     */
    private function driversForTripForm(int $schoolId, ?string $tripType): Collection
    {
        if ($schoolId <= 0) {
            return collect();
        }

        $query = Driver::query()
            ->with('bus')
            ->where('school_id', $schoolId)
            ->where('status', 'active')
            ->orderBy('first_name')
            ->orderBy('last_name');

        $shift = $this->studentShiftFilter->shiftFromTripType($tripType);
        if ($shift !== null) {
            $query->where(function (Builder $q) use ($shift): void {
                $q->whereNull('shift_period')
                    ->orWhere('shift_period', $shift)
                    ->orWhere('shift_period', 'BOTH');
            });
        }

        return $query->get();
    }

    /**
     * @return array{id: int, label: string}
     */
    private function studentOptionRow(Student $student): array
    {
        $shift = strtoupper(trim((string) ($student->shift_period ?? '')));
        $shiftLabel = match ($shift) {
            DriverShiftResolver::MORNING => __('dashboard.student_shift_period_morning'),
            DriverShiftResolver::EVENING => __('dashboard.student_shift_period_evening'),
            default => __('dashboard.student_shift_period_unspecified'),
        };

        return [
            'id' => (int) $student->id,
            'label' => trim((string) $student->full_name).' — '.trim((string) $student->grade).' ('.$shiftLabel.') #'.$student->id,
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     label: string,
     *     bus_number: string|null,
     *     students_count: int,
     *     route_title: string|null,
     *     location: string|null,
     *     distance_km: float|null,
     *     transport_route_id: int|null,
     *     route_student_ids: list<int>,
     *     start_address: string|null,
     *     end_address: string|null,
     *     route_start_latitude: float|null,
     *     route_start_longitude: float|null,
     *     school_latitude: float|null,
     *     school_longitude: float|null
     * }
     */
    private function driverOptionRow(Driver $driver, ?\App\Models\TransportRoute $transportRoute = null): array
    {
        $bus = $driver->relationLoaded('bus') ? $driver->bus : $driver->bus()->first();
        $routePayload = $this->tripTransportRouteApplier->driverRouteFormPayload($transportRoute) ?? [];
        $routeStudentIds = $routePayload['route_student_ids'] ?? [];
        $routeStudentCount = is_array($routeStudentIds) ? count($routeStudentIds) : 0;

        return [
            'id' => (int) $driver->id,
            'label' => trim(($driver->first_name ?? '').' '.($driver->last_name ?? '')).' (#'.(int) $driver->id.')',
            'bus_number' => $bus?->number !== null && trim((string) $bus->number) !== ''
                ? trim((string) $bus->number)
                : null,
            'students_count' => $routeStudentCount > 0
                ? $routeStudentCount
                : ($bus !== null ? max(0, (int) $bus->capacity) : 0),
            'route_title' => $routePayload['route_title'] ?? null,
            'location' => $routePayload['location'] ?? null,
            'distance_km' => isset($routePayload['distance_km']) ? (float) $routePayload['distance_km'] : null,
            'transport_route_id' => isset($routePayload['transport_route_id']) ? (int) $routePayload['transport_route_id'] : null,
            'route_student_ids' => $routePayload['route_student_ids'] ?? [],
            'start_address' => $routePayload['start_address'] ?? null,
            'end_address' => $routePayload['end_address'] ?? null,
            'route_start_latitude' => $routePayload['route_start_latitude'] ?? null,
            'route_start_longitude' => $routePayload['route_start_longitude'] ?? null,
            'school_latitude' => $routePayload['school_latitude'] ?? null,
            'school_longitude' => $routePayload['school_longitude'] ?? null,
        ];
    }
}

