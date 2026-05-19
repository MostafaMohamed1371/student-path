<?php

namespace App\Http\Controllers\Web;

use App\Enums\StudentTripStopStatus;
use App\Enums\TripType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\ManagesDashboardScoping;
use App\Models\Driver;
use App\Models\School;
use App\Models\Student;
use App\Models\TripHistory;
use App\Models\TripHistoryStudent;
use App\Models\TransportRoute;
use App\Services\Routes\RouteAssignmentPlanner;
use App\Services\Trips\DriverShiftResolver;
use App\Services\Trips\DriverTripModuleService;
use App\Services\Trips\StudentShiftFilter;
use App\Services\Trips\TripStudentAvailability;
use App\Services\Trips\TripTransportRouteApplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DashboardTripController extends Controller
{
    use ManagesDashboardScoping;

    public function __construct(
        private readonly StudentShiftFilter $studentShiftFilter,
        private readonly TripStudentAvailability $tripStudentAvailability,
        private readonly TripTransportRouteApplier $tripTransportRouteApplier,
        private readonly RouteAssignmentPlanner $routeAssignmentPlanner,
    ) {}

    public function index(): View
    {
        $trips = TripHistory::query()
            ->with(['school', 'driver'])
            ->tap(fn (Builder $q) => $this->constrainToScopingSchool($q))
            ->orderByDesc('start_time')
            ->paginate(12);

        return view('dashboard.trips.index', compact('trips'));
    }

    public function show(TripHistory $trip, DriverTripModuleService $driverTripModule): View
    {
        abort_unless($this->tripHistoryVisible($trip), 404);
        $trip->load(['school', 'driver', 'tripHistoryStudents.student']);

        $tripDetail = $driverTripModule->tripDetailPayload($trip);

        return view('dashboard.trips.show', compact('trip', 'tripDetail'));
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

        unset($validated['student_ids']);

        $validated = $this->tripTransportRouteApplier->applyRouteToTripAttributes($validated);
        $this->assertTripHasDriver($validated);
        $validated['students_count'] = 0;
        $validated['students_preview'] = [];

        $trip = TripHistory::query()->create($validated);

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

        return view('dashboard.trips.edit', [
            'trip' => $trip,
            'schools' => $schools,
            'drivers' => $drivers,
            'tripTypes' => $tripTypes,
            'formOptionsUrl' => route('dashboard.trips.form_options'),
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
        $tripId = (int) $request->query('trip_id', 0);

        $drivers = $schoolId > 0 ? $this->driversForTripForm($schoolId, null) : collect();
        $trips = $schoolId > 0 ? $this->tripsForAssignForm($schoolId, $driverId > 0 ? $driverId : null) : collect();

        $trip = null;
        $students = collect();
        $selectedStudentIds = [];
        if ($tripId > 0) {
            $trip = TripHistory::query()->with(['driver', 'school'])->find($tripId);
            if ($trip && $this->tripHistoryVisible($trip)) {
                $schoolId = (int) $trip->school_id;
                $selectedStudentIds = $trip->tripHistoryStudents()->pluck('student_id')->map(fn ($id): int => (int) $id)->all();
                $students = $this->studentsForTripForm(
                    $schoolId,
                    is_string($trip->trip_type) ? $trip->trip_type : null,
                    $selectedStudentIds,
                    (int) $trip->id,
                    $trip->driver_id !== null ? (int) $trip->driver_id : null,
                );
            } else {
                $trip = null;
                $tripId = 0;
            }
        }

        return view('dashboard.trips.assign_students', [
            'schools' => $schools,
            'drivers' => $drivers,
            'trips' => $trips,
            'trip' => $trip,
            'students' => $students,
            'selectedStudentIds' => $selectedStudentIds,
            'schoolId' => $schoolId,
            'driverId' => $driverId,
            'tripId' => $tripId,
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
            'include_student_ids' => ['nullable', 'array'],
            'include_student_ids.*' => ['integer'],
        ]);

        $schoolId = (int) $validated['school_id'];
        $this->abortUnlessCanMutateSchoolRosterForSchool($schoolId);

        $driverId = isset($validated['driver_id']) ? (int) $validated['driver_id'] : 0;
        if ($driverId > 0) {
            $this->assertDriverBelongsToSchool($driverId, $schoolId);
        }

        $tripId = isset($validated['trip_id']) ? (int) $validated['trip_id'] : 0;
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
        $tripPayload = null;
        $routeFilterActive = false;
        $corridorMaxKm = null;

        if ($tripId > 0) {
            $trip = TripHistory::query()->with('driver')->find($tripId);
            if ($trip && $this->tripHistoryVisible($trip) && (int) $trip->school_id === $schoolId) {
                $tripType = is_string($trip->trip_type) && $trip->trip_type !== '' ? $trip->trip_type : null;
                $tripDriverId = $trip->driver_id !== null ? (int) $trip->driver_id : null;
                $selectedStudentIds = $trip->tripHistoryStudents()->pluck('student_id')->map(fn ($id): int => (int) $id)->all();
                $includeIds = array_values(array_unique(array_merge($includeIds, $selectedStudentIds)));

                $students = $this->studentsForTripForm($schoolId, $tripType, $includeIds, $tripId, $tripDriverId)
                    ->map(fn (Student $s): array => $this->studentOptionRow($s))
                    ->values()
                    ->all();

                $activeRoute = $this->transportRouteForDriver($schoolId, $tripType, $tripDriverId);
                $routeFilterActive = $activeRoute !== null;
                $corridorMaxKm = $routeFilterActive
                    ? round((float) config('routes.corridor_max_meters', 3000) / 1000, 1)
                    : null;

                $tripPayload = [
                    'id' => (int) $trip->id,
                    'trip_type' => $trip->trip_type,
                    'driver_id' => $trip->driver_id,
                    'route_title' => $trip->route_title,
                    'start_time' => $trip->start_time?->format('Y-m-d H:i'),
                    'students_count' => (int) $trip->students_count,
                ];
            }
        }

        return response()->json([
            'drivers' => $drivers,
            'trips' => $trips,
            'trip' => $tripPayload,
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
            'trip_id' => ['required', 'integer', 'exists:trip_histories,id'],
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['integer', 'exists:students,id'],
        ]);

        $trip = TripHistory::query()->findOrFail((int) $validated['trip_id']);
        abort_unless($this->tripHistoryVisible($trip), 404);
        $this->abortUnlessCanMutateSchoolRosterForSchool((int) $trip->school_id);

        if (in_array(strtoupper((string) $trip->status), ['CANCELLED', 'COMPLETED'], true)) {
            throw ValidationException::withMessages([
                'trip_id' => [__('dashboard.trip_assign_students_closed_trip')],
            ]);
        }

        $studentIds = array_values(array_unique(array_map(static fn ($v): int => (int) $v, $validated['student_ids'])));
        $this->syncTripStudentsForSchool($trip, $studentIds, (int) $trip->school_id);

        return redirect()
            ->route('dashboard.trips.assign_students', [
                'school_id' => $trip->school_id,
                'trip_id' => $trip->id,
            ])
            ->with('success', __('dashboard.trip_students_assigned', ['count' => count($studentIds)]));
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

        unset($validated['student_ids']);

        $validated = $this->tripTransportRouteApplier->applyRouteToTripAttributes($validated);

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
            'students_count' => [$required, 'integer', 'min:0'],
            'distance_km' => [$required, 'numeric', 'min:0'],
            'start_time' => [$required, 'date'],
            'end_time' => ['nullable', 'date', 'after_or_equal:start_time'],
            'status' => [$required, 'in:'.$statusValues],
            'note' => ['nullable', 'string'],
            'student_ids' => [$partial ? 'sometimes' : 'nullable', 'array'],
            'student_ids.*' => ['integer', 'exists:students,id'],
        ];
    }

    /**
     * @param  list<int|string>  $studentIds
     */
    private function syncTripStudentsForSchool(TripHistory $trip, array $studentIds, int $schoolId): void
    {
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

        $this->tripStudentAvailability->assertStudentsAvailableForTrip($unique, $schoolId, (int) $trip->id);

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
        ?int $exceptTripId = null,
        ?int $driverId = null,
    ): Collection {
        if ($schoolId <= 0) {
            return collect();
        }

        $bookedIds = $this->tripStudentAvailability->studentIdsOnActiveTrips($schoolId, $exceptTripId);

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
     *     end_address: string|null
     * }
     */
    private function driverOptionRow(Driver $driver, ?\App\Models\TransportRoute $transportRoute = null): array
    {
        $bus = $driver->relationLoaded('bus') ? $driver->bus : $driver->bus()->first();
        $routePayload = $this->tripTransportRouteApplier->driverRouteFormPayload($transportRoute) ?? [];

        return [
            'id' => (int) $driver->id,
            'label' => trim(($driver->first_name ?? '').' '.($driver->last_name ?? '')).' (#'.(int) $driver->id.')',
            'bus_number' => $bus?->number !== null && trim((string) $bus->number) !== ''
                ? trim((string) $bus->number)
                : null,
            'students_count' => $bus !== null ? max(0, (int) $bus->capacity) : 0,
            'route_title' => $routePayload['route_title'] ?? null,
            'location' => $routePayload['location'] ?? null,
            'distance_km' => isset($routePayload['distance_km']) ? (float) $routePayload['distance_km'] : null,
            'transport_route_id' => isset($routePayload['transport_route_id']) ? (int) $routePayload['transport_route_id'] : null,
            'route_student_ids' => $routePayload['route_student_ids'] ?? [],
            'start_address' => $routePayload['start_address'] ?? null,
            'end_address' => $routePayload['end_address'] ?? null,
        ];
    }
}

