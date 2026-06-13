<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\ConstrainsDashboardUserScope;
use App\Http\Controllers\Web\Concerns\ManagesDashboardScoping;
use App\Http\Controllers\Web\Concerns\ProvidesDashboardSchoolDriverFilters;
use App\Http\Controllers\Web\Concerns\ScopesDashboardTripRequests;
use App\Http\Requests\Web\StoreDashboardTripRequestRequest;
use App\Http\Requests\Web\UpdateDashboardTripRequestRequest;
use App\Http\Requests\Web\UpdateDashboardTripRequestStatusRequest;
use App\Models\Driver;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\TripHistory;
use App\Models\TripRequest;
use App\Models\User;
use App\Services\Trips\TripRequestAcceptanceService;
use App\Services\Trips\TripRequestConflictGuard;
use App\Services\Trips\TripRequestCreator;
use App\Services\Trips\TripRequestSubmissionPlanner;
use App\Services\Trips\DriverShiftResolver;
use App\Support\ParentContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DashboardTripRequestController extends Controller
{
    use ConstrainsDashboardUserScope;
    use ManagesDashboardScoping;
    use ProvidesDashboardSchoolDriverFilters;
    use ScopesDashboardTripRequests;

    public function index(Request $request): View
    {
        $isDriverUser = ! (bool) auth()->user()?->is_admin && $this->currentDriver() instanceof Driver;
        $filters = $this->dashboardReportFilterContext($request);

        $query = $this->tripRequestListQuery()->latest('trip_requests.id');
        $this->applyTripRequestDashboardScope(
            $query,
            $filters['effectiveSchoolId'] > 0 ? $filters['effectiveSchoolId'] : null,
            $filters['filterDriverId'] > 0 ? $filters['filterDriverId'] : null,
        );

        $tripRequests = $query->paginate($this->dashboardListPerPage())->withQueryString();

        return view('dashboard.trip-requests.index', array_merge($filters, [
            'filterAction' => route('dashboard.trip_requests.index'),
            'tripRequests' => $tripRequests,
            'showSchoolFilter' => ! $isDriverUser && $filters['showSchoolFilter'],
            'showDriverFilter' => ! $isDriverUser && $filters['showDriverFilter'],
            'showSchoolColumn' => (bool) auth()->user()?->is_admin,
            'canManageTripRequests' => (bool) auth()->user()?->canMutateSchoolRoster() && ! $isDriverUser,
        ]));
    }

    public function create(): View
    {
        $this->abortUnlessCanManageTripRequestsInDashboard();

        return view('dashboard.trip-requests.create', [
            'schools' => $this->schoolsForRosterForm(),
            'formOptionsUrl' => route('dashboard.trip_requests.form_options'),
            'formStudentsUrl' => route('dashboard.trip_requests.form_students'),
        ]);
    }

    public function formOptions(Request $request): JsonResponse
    {
        $this->abortUnlessCanManageTripRequestsInDashboard();

        $validated = $request->validate([
            'school_id' => ['required', 'integer', 'exists:schools,id'],
        ]);

        $schoolId = (int) $validated['school_id'];
        $this->abortUnlessCanMutateSchoolRosterForSchool($schoolId);

        $trips = TripHistory::query()
            ->where('school_id', $schoolId)
            ->orderByDesc('start_time')
            ->limit(200)
            ->get()
            ->map(fn (TripHistory $t): array => [
                'id' => (int) $t->id,
                'label' => '#'.$t->id.' — '.($t->route_title ?: $t->bus_number).' ('.$t->trip_type.')',
            ])
            ->values()
            ->all();

        return response()->json([
            'parents' => $this->parentOptionsForSchool($schoolId),
            'trips' => $trips,
        ]);
    }

    public function formStudents(Request $request): JsonResponse
    {
        $this->abortUnlessCanManageTripRequestsInDashboard();

        $validated = $request->validate([
            'school_id' => ['required', 'integer', 'exists:schools,id'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $schoolId = (int) $validated['school_id'];
        $userId = (int) $validated['user_id'];
        $this->abortUnlessCanMutateSchoolRosterForSchool($schoolId);
        abort_unless($this->userIdVisibleInDashboardScope($userId), 403);

        $user = User::query()->findOrFail($userId);

        $students = ParentContext::studentsFor($user)
            ->filter(fn (Student $student): bool => (int) $student->school_id === $schoolId)
            ->map(fn (Student $student): array => [
                'id' => (int) $student->id,
                'label' => $student->full_name.' (#'.$student->id.')',
            ])
            ->values()
            ->all();

        return response()->json(['students' => $students]);
    }

    public function store(StoreDashboardTripRequestRequest $request): RedirectResponse
    {
        $this->abortUnlessCanManageTripRequestsInDashboard();

        $validated = $this->enforceRosterSchoolIdForStaff($request->validated());
        $schoolId = (int) $validated['school_id'];
        $this->abortUnlessCanMutateSchoolRosterForSchool($schoolId);

        $user = User::query()->findOrFail((int) $validated['user_id']);
        abort_unless($this->userIdVisibleInDashboardScope((int) $user->id), 403);
        abort_unless($this->userIsParentAtSchool($user, $schoolId), 403);

        $student = Student::query()
            ->where('school_id', $schoolId)
            ->whereKey((int) $validated['student_id'])
            ->firstOrFail();
        ParentContext::ensureUserLinkedToStudent($user, $student);
        abort_unless(ParentContext::ownsStudent($user, $student->id), 403);

        $trip = TripHistory::query()
            ->where('school_id', $schoolId)
            ->whereKey((int) $validated['trip_history_id'])
            ->firstOrFail();

        try {
            $plan = app(TripRequestSubmissionPlanner::class)->plan(
                $user,
                $student,
                $trip,
            );
        } catch (ValidationException $e) {
            return redirect()->back()->withInput()->withErrors($e->errors());
        }

        [$row, $created] = app(TripRequestCreator::class)->createOrReturnExistingPending(
            $user,
            $student,
            $plan->driverId,
            [
                'trip_history_id' => $plan->tripHistoryId,
                'status' => 'pending',
                'notes' => $validated['notes'] ?? null,
                ...$plan->snapshot,
            ],
        );

        $message = $created
            ? __('dashboard.trip_request_created')
            : __('dashboard.trip_request_already_pending');

        return redirect()
            ->route('dashboard.trip_requests.show', $row)
            ->with('success', $message);
    }

    public function show(TripRequest $trip_request): View
    {
        $trip_request->loadMissing(['user.guardian', 'student', 'student.guardian', 'student.school', 'driver', 'tripHistory']);
        abort_unless($this->tripRequestVisible($trip_request), 404);

        return view('dashboard.trip-requests.show', ['tripRequest' => $trip_request]);
    }

    public function edit(TripRequest $trip_request): View
    {
        $this->abortUnlessCanManageTripRequestsInDashboard();
        abort_unless($this->tripRequestVisible($trip_request), 404);
        $trip_request->load(['user.guardian', 'student.guardian', 'student.school', 'driver', 'tripHistory']);

        $schoolId = (int) ($trip_request->student?->school_id ?? $trip_request->driver?->school_id ?? 0);
        $students = $this->studentsForUser($trip_request->user)->get();
        $trips = $this->tripHistoriesInScope()
            ->when($schoolId > 0, fn (Builder $q) => $q->where('school_id', $schoolId))
            ->get();
        $drivers = Driver::query()
            ->when($schoolId > 0, fn (Builder $q) => $q->where('school_id', $schoolId))
            ->where('status', 'active')
            ->orderBy('first_name')
            ->orderBy('id')
            ->get();

        return view('dashboard.trip-requests.edit', [
            'trip_request' => $trip_request,
            'students' => $students,
            'trips' => $trips,
            'drivers' => $drivers,
            'statusOptions' => $this->tripRequestStatusOptions(),
        ]);
    }

    public function update(UpdateDashboardTripRequestRequest $request, TripRequest $trip_request): RedirectResponse
    {
        $this->abortUnlessCanManageTripRequestsInDashboard();
        abort_unless($this->tripRequestVisible($trip_request), 404);

        $validated = $request->validated();
        $user = $trip_request->user;
        $previousStatus = (string) $trip_request->status;
        $nextStatus = (string) $validated['status'];

        $student = $this->studentsInScope()->whereKey((int) $validated['student_id'])->firstOrFail();
        abort_unless(ParentContext::ownsStudent($user, $student->id), 403);

        $tripHistoryId = array_key_exists('trip_history_id', $validated)
            ? $validated['trip_history_id']
            : $trip_request->trip_history_id;
        $trip = null;
        if ($tripHistoryId !== null && (int) $tripHistoryId > 0) {
            $trip = TripHistory::query()->findOrFail((int) $tripHistoryId);
            $this->assertTripInScope($trip);
            try {
                app(TripRequestSubmissionPlanner::class)->assertTripMatchesStudentSchools($user, $student, $trip);
                app(TripRequestSubmissionPlanner::class)->assertStudentShiftMatchesTrip($student, $trip);
            } catch (ValidationException $e) {
                return redirect()->back()->withInput()->withErrors($e->errors());
            }
        }

        $presentType = $validated['present_type'] ?? $trip_request->present_type;
        $driverId = array_key_exists('driver_id', $validated) && $validated['driver_id'] !== null && $validated['driver_id'] !== ''
            ? (int) $validated['driver_id']
            : $trip_request->driver_id;

        if ($driverId !== null && (int) $driverId > 0) {
            try {
                app(TripRequestSubmissionPlanner::class)->plan(
                    $user,
                    $student,
                    $trip,
                    (int) $driverId,
                    $presentType,
                );
            } catch (ValidationException $e) {
                return redirect()->back()->withInput()->withErrors($e->errors());
            }
        } elseif ($driverId === null && ($previousStatus !== $nextStatus || (int) $student->id !== (int) $trip_request->student_id)) {
            $targetShift = app(DriverShiftResolver::class)->fromPresentType($presentType);
            if ($targetShift === null && $trip !== null) {
                $targetShift = app(DriverShiftResolver::class)->fromTripType($trip->trip_type);
            }
            $driverId = $this->assignSchoolDriverId((int) $student->school_id, $targetShift);
        }

        $attributes = [
            'student_id' => $student->id,
            'driver_id' => $driverId,
            'trip_history_id' => $tripHistoryId,
            'notes' => $validated['notes'] ?? null,
            'present_type' => $presentType,
            'moving_point' => $validated['moving_point'] ?? null,
            'stop_point' => $validated['stop_point'] ?? null,
            'subscribe_price' => array_key_exists('subscribe_price', $validated)
                ? $validated['subscribe_price']
                : $trip_request->subscribe_price,
        ];

        if ($previousStatus === 'pending' && in_array($nextStatus, ['accepted', 'rejected'], true)) {
            $trip_request->fill($attributes)->save();

            try {
                app(TripRequestAcceptanceService::class)->applyDriverDecision($trip_request->fresh(), $nextStatus);
            } catch (ValidationException $e) {
                return redirect()->back()->withInput()->withErrors($e->errors());
            }

            return redirect()->route('dashboard.trip_requests.show', $trip_request)
                ->with('success', __('dashboard.trip_request_updated'));
        }

        $attributes['status'] = $nextStatus;
        if ($nextStatus === 'cancelled') {
            $attributes['cancelled_at'] = $trip_request->cancelled_at ?? now();
        } elseif ($previousStatus === 'cancelled' && $nextStatus !== 'cancelled') {
            $attributes['cancelled_at'] = null;
        }

        $trip_request->fill($attributes)->save();

        return redirect()->route('dashboard.trip_requests.show', $trip_request)
            ->with('success', __('dashboard.trip_request_updated'));
    }

    public function updateStatus(UpdateDashboardTripRequestStatusRequest $request, TripRequest $trip_request): RedirectResponse
    {
        abort_unless($this->tripRequestVisible($trip_request), 404);
        $driver = $this->currentDriver();
        if ($driver instanceof Driver && (int) $trip_request->driver_id !== (int) $driver->id) {
            abort(403);
        }

        if ($trip_request->status !== 'pending') {
            $nextStatus = (string) $request->validated('status');
            $conflictGuard = app(TripRequestConflictGuard::class);
            if ($nextStatus === 'accepted' && $conflictGuard->slotTakenByAnotherDriver($trip_request)) {
                $conflictGuard->closePendingRequestWhenSlotTaken($trip_request);

                return redirect()
                    ->route('dashboard.trip_requests.show', $trip_request->fresh())
                    ->with('error', __('dashboard.trip_request_slot_taken_by_another_driver'));
            }

            return redirect()
                ->route('dashboard.trip_requests.show', $trip_request)
                ->with('error', __('dashboard.trip_request_only_pending_status'));
        }

        $nextStatus = (string) $request->validated('status');

        try {
            app(TripRequestAcceptanceService::class)->applyDriverDecision($trip_request, $nextStatus);
        } catch (ValidationException $e) {
            return redirect()
                ->route('dashboard.trip_requests.show', $trip_request->fresh())
                ->with('error', collect($e->errors())->flatten()->first());
        }

        return redirect()
            ->route('dashboard.trip_requests.show', $trip_request)
            ->with('success', __('dashboard.trip_request_status_updated'));
    }

    public function destroy(TripRequest $trip_request): RedirectResponse
    {
        $this->abortUnlessCanManageTripRequestsInDashboard();
        abort_unless($this->tripRequestVisible($trip_request), 404);

        $trip_request->delete();

        return redirect()->route('dashboard.trip_requests.index')
            ->with('success', __('dashboard.trip_request_deleted'));
    }

    private function tripRequestVisible(TripRequest $tripRequest): bool
    {
        $auth = auth()->user();
        if (! $auth) {
            return false;
        }
        if ($auth->is_admin) {
            return true;
        }
        $driver = $this->currentDriver();
        if ($driver instanceof Driver) {
            return (int) ($tripRequest->driver_id ?? 0) === (int) $driver->id;
        }
        $sid = $auth->scopingSchoolId();
        if ($sid === null) {
            return false;
        }

        if ($tripRequest->student !== null && (int) $tripRequest->student->school_id === (int) $sid) {
            return true;
        }

        if ($tripRequest->driver !== null && (int) $tripRequest->driver->school_id === (int) $sid) {
            return true;
        }

        if ($tripRequest->user !== null) {
            if ((int) ($tripRequest->user->school_id ?? 0) === (int) $sid) {
                return true;
            }
            $tripRequest->user->loadMissing('guardian');
            if ($tripRequest->user->guardian !== null && (int) $tripRequest->user->guardian->school_id === (int) $sid) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    private function parentOptionsForSchool(int $schoolId): array
    {
        $options = [];
        $seenUserIds = [];

        $guardians = Guardian::query()
            ->where('school_id', $schoolId)
            ->orderBy('full_name')
            ->get();

        foreach ($guardians as $guardian) {
            $phoneNational = preg_replace('/\D+/', '', (string) $guardian->phone) ?? '';
            $phoneE164 = $phoneNational !== '' ? '964'.$phoneNational : '';

            $users = User::query()
                ->where(function (Builder $q) use ($guardian, $phoneNational, $phoneE164): void {
                    $q->where('guardian_id', $guardian->id);
                    if ($phoneNational !== '') {
                        $q->orWhere('phone', $phoneNational)
                            ->orWhere('phone', $phoneE164);
                    }
                })
                ->orderBy('id')
                ->get();

            foreach ($users as $user) {
                if (isset($seenUserIds[(int) $user->id])) {
                    continue;
                }
                if (! auth()->user()?->is_admin && ! $this->userIdVisibleInDashboardScope((int) $user->id)) {
                    continue;
                }

                $seenUserIds[(int) $user->id] = true;
                $displayName = trim((string) ($user->name ?: $guardian->full_name));
                $options[] = [
                    'id' => (int) $user->id,
                    'label' => $displayName.' — '.(string) $user->phone.' (#'.$user->id.')',
                ];
            }
        }

        usort($options, fn (array $a, array $b): int => strcasecmp($a['label'], $b['label']));

        return $options;
    }

    private function userIsParentAtSchool(User $user, int $schoolId): bool
    {
        foreach ($this->parentOptionsForSchool($schoolId) as $option) {
            if ((int) $option['id'] === (int) $user->id) {
                return true;
            }
        }

        return false;
    }

    private function studentsInScope(): Builder
    {
        $q = Student::query()->orderBy('full_name');
        if (! auth()->user()?->is_admin) {
            $sid = auth()->user()?->scopingSchoolId();
            if ($sid === null) {
                $q->whereRaw('0 = 1');
            } else {
                $q->where('school_id', $sid);
            }
        }

        return $q;
    }

    private function studentsForUser(User $user): Builder
    {
        $ids = ParentContext::studentIdsFor($user);
        $q = Student::query()->whereIn('id', $ids !== [] ? $ids : [0])->orderBy('full_name');
        if (! auth()->user()?->is_admin) {
            $sid = auth()->user()?->scopingSchoolId();
            if ($sid === null) {
                $q->whereRaw('0 = 1');
            } else {
                $q->where('school_id', $sid);
            }
        }

        return $q;
    }

    private function tripHistoriesInScope(): Builder
    {
        $q = TripHistory::query()->orderByDesc('start_time')->limit(200);
        if (! auth()->user()?->is_admin) {
            $sid = auth()->user()?->scopingSchoolId();
            if ($sid === null) {
                $q->whereRaw('0 = 1');
            } else {
                $q->where('school_id', $sid);
            }
        }

        return $q;
    }

    private function assertTripInScope(TripHistory $trip): void
    {
        if (auth()->user()?->is_admin) {
            return;
        }
        $sid = auth()->user()?->scopingSchoolId();
        abort_unless($sid !== null && (int) $trip->school_id === (int) $sid, 403);
    }

    private function assignSchoolDriverId(int $schoolId, ?string $targetShift = null): ?int
    {
        $query = Driver::query()
            ->where('school_id', $schoolId)
            ->where('status', 'active')
            ->orderBy('id');

        if ($targetShift !== null) {
            $matching = (clone $query)
                ->where(function ($q) use ($targetShift): void {
                    $q->where('shift_period', $targetShift)->orWhere('shift_period', 'BOTH');
                })
                ->value('id');
            if ($matching !== null) {
                return $matching;
            }
        }

        return $query->value('id');
    }

    protected function currentDriver(): ?Driver
    {
        $authId = auth()->id();
        if (! is_int($authId)) {
            return null;
        }

        return Driver::query()->where('user_id', $authId)->first();
    }

    /**
     * @return array<string, string>
     */
    private function tripRequestStatusOptions(): array
    {
        return [
            'pending' => __('dashboard.trip_request_status_pending'),
            'accepted' => __('dashboard.trip_request_status_accepted'),
            'rejected' => __('dashboard.trip_request_status_rejected'),
            'cancelled' => __('dashboard.trip_request_status_cancelled'),
        ];
    }

    /**
     * Drivers use trip requests read-only (accept/reject). Admins may also have a driver row.
     */
    private function abortUnlessCanManageTripRequestsInDashboard(): void
    {
        abort_unless(auth()->user()?->canMutateSchoolRoster(), 403);
        abort_if(
            ! (bool) auth()->user()?->is_admin && $this->currentDriver() instanceof Driver,
            403,
        );
    }
}
