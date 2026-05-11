<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\ConstrainsDashboardUserScope;
use App\Http\Requests\Web\StoreDashboardTripRequestRequest;
use App\Http\Requests\Web\UpdateDashboardTripRequestRequest;
use App\Http\Requests\Web\UpdateDashboardTripRequestStatusRequest;
use App\Models\Driver;
use App\Models\Student;
use App\Models\TripHistory;
use App\Models\TripRequest;
use App\Models\User;
use App\Services\Trips\TripRequestAcceptanceService;
use App\Services\Trips\DriverShiftResolver;
use App\Services\Trips\TripRequestOrderSnapshot;
use App\Support\ParentContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardTripRequestController extends Controller
{
    use ConstrainsDashboardUserScope;

    public function index(Request $request): View
    {
        $perPage = min(100, max(5, (int) $request->query('per_page', 25)));

        $query = TripRequest::query()
            ->with(['user', 'student', 'driver', 'tripHistory'])
            ->latest('trip_requests.id');

        if ($this->currentDriver() instanceof Driver) {
            $query->where('driver_id', $this->currentDriver()?->id);
        } elseif (! auth()->user()?->is_admin) {
            $sid = auth()->user()?->scopingSchoolId();
            if ($sid === null) {
                $query->whereRaw('0 = 1');
            } else {
                $query->whereHas('student', fn (Builder $q) => $q->where('school_id', $sid));
            }
        }

        $tripRequests = $query->paginate($perPage);

        return view('dashboard.trip-requests.index', compact('tripRequests'));
    }

    public function create(): View
    {
        abort_if($this->currentDriver() instanceof Driver, 403);

        $users = $this->usersInScope()->get();
        $students = $this->studentsInScope()->get();
        $trips = $this->tripHistoriesInScope()->get();

        return view('dashboard.trip-requests.create', compact('users', 'students', 'trips'));
    }

    public function store(StoreDashboardTripRequestRequest $request): RedirectResponse
    {
        abort_if($this->currentDriver() instanceof Driver, 403);

        $validated = $request->validated();
        $user = User::query()->findOrFail((int) $validated['user_id']);
        abort_unless($this->userIdVisibleInDashboardScope((int) $user->id), 403);

        $student = $this->studentsInScope()->whereKey((int) $validated['student_id'])->firstOrFail();
        abort_unless(ParentContext::ownsStudent($user, $student->id), 403);

        $tripHistoryId = $validated['trip_history_id'] ?? null;
        if ($tripHistoryId !== null) {
            $trip = TripHistory::query()->findOrFail((int) $tripHistoryId);
            $this->assertTripInScope($trip);
            $schoolIds = ParentContext::studentsFor($user)->pluck('school_id')->unique()->filter();
            if ($schoolIds->isNotEmpty() && ! $schoolIds->contains($trip->school_id)) {
                return redirect()->back()->withInput()->with('error', __('dashboard.trip_request_trip_out_of_scope'));
            }
        }

        $targetShift = null;
        if ($tripHistoryId !== null) {
            $tripForShift = TripHistory::query()->find((int) $tripHistoryId);
            $targetShift = app(DriverShiftResolver::class)->fromTripType($tripForShift?->trip_type);
        }

        $driverId = $this->assignSchoolDriverId((int) $student->school_id, $targetShift);
        $assignedDriver = Driver::query()->find($driverId);
        $snapshot = TripRequestOrderSnapshot::build($student, $assignedDriver, []);

        TripRequest::query()->create([
            'user_id' => $user->id,
            'student_id' => $student->id,
            'driver_id' => $driverId,
            'trip_history_id' => $tripHistoryId,
            'status' => 'pending',
            'notes' => $validated['notes'] ?? null,
            ...$snapshot,
        ]);

        return redirect()->route('dashboard.trip_requests.index')
            ->with('success', __('dashboard.trip_request_created'));
    }

    public function show(TripRequest $trip_request): View
    {
        abort_unless($this->tripRequestVisible($trip_request), 404);
        $trip_request->load(['user', 'student', 'driver', 'tripHistory']);

        return view('dashboard.trip-requests.show', ['tripRequest' => $trip_request]);
    }

    public function edit(TripRequest $trip_request): View
    {
        abort_if($this->currentDriver() instanceof Driver, 403);
        abort_unless($this->tripRequestVisible($trip_request), 404);
        $trip_request->load(['user', 'student', 'driver', 'tripHistory']);
        $students = $this->studentsForUser($trip_request->user)->get();
        $trips = $this->tripHistoriesInScope()->get();

        return view('dashboard.trip-requests.edit', compact('trip_request', 'students', 'trips'));
    }

    public function update(UpdateDashboardTripRequestRequest $request, TripRequest $trip_request): RedirectResponse
    {
        abort_if($this->currentDriver() instanceof Driver, 403);
        abort_unless($this->tripRequestVisible($trip_request), 404);

        if ($trip_request->status !== 'pending') {
            return redirect()
                ->route('dashboard.trip_requests.edit', $trip_request)
                ->with('error', __('dashboard.trip_request_only_pending_status'));
        }

        $validated = $request->validated();
        $user = $trip_request->user;
        if (isset($validated['student_id'])) {
            $student = $this->studentsInScope()->whereKey((int) $validated['student_id'])->firstOrFail();
            abort_unless(ParentContext::ownsStudent($user, $student->id), 403);
        }

        $tripHistoryId = array_key_exists('trip_history_id', $validated)
            ? $validated['trip_history_id']
            : $trip_request->trip_history_id;

        if ($tripHistoryId !== null) {
            $trip = TripHistory::query()->findOrFail((int) $tripHistoryId);
            $this->assertTripInScope($trip);
            $schoolIds = ParentContext::studentsFor($user)->pluck('school_id')->unique()->filter();
            if ($schoolIds->isNotEmpty() && ! $schoolIds->contains($trip->school_id)) {
                return redirect()->back()->withInput()->with('error', __('dashboard.trip_request_trip_out_of_scope'));
            }
        }

        if (isset($validated['student_id'])) {
            $updatedStudent = Student::query()->find((int) $validated['student_id']);
            if ($updatedStudent) {
                $targetShift = null;
                if ($tripHistoryId !== null) {
                    $tripForShift = TripHistory::query()->find((int) $tripHistoryId);
                    $targetShift = app(DriverShiftResolver::class)->fromTripType($tripForShift?->trip_type);
                }
                if ($targetShift === null) {
                    $targetShift = app(DriverShiftResolver::class)->fromPresentType($trip_request->present_type);
                }
                $validated['driver_id'] = $this->assignSchoolDriverId((int) $updatedStudent->school_id, $targetShift);
            }
        }

        $trip_request->fill($validated)->save();

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
            return redirect()
                ->route('dashboard.trip_requests.show', $trip_request)
                ->with('error', __('dashboard.trip_request_only_pending_status'));
        }

        $nextStatus = (string) $request->validated('status');

        app(TripRequestAcceptanceService::class)->applyDriverDecision($trip_request, $nextStatus);

        return redirect()
            ->route('dashboard.trip_requests.show', $trip_request)
            ->with('success', __('dashboard.trip_request_status_updated'));
    }

    public function destroy(TripRequest $trip_request): RedirectResponse
    {
        abort_if($this->currentDriver() instanceof Driver, 403);
        abort_unless($this->tripRequestVisible($trip_request), 404);

        if ($trip_request->status !== 'pending') {
            return redirect()
                ->route('dashboard.trip_requests.index')
                ->with('error', __('dashboard.trip_request_only_pending_delete'));
        }

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

        return $tripRequest->student !== null
            && (int) $tripRequest->student->school_id === (int) $sid;
    }

    private function usersInScope(): Builder
    {
        $q = User::query()->orderBy('name');
        if (! auth()->user()?->is_admin) {
            $q->tap(fn (Builder $b) => $this->constrainUsersToDashboardScope($b));
        }

        return $q;
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
            $matching = (clone $query)->where('shift_period', $targetShift)->value('id');
            if ($matching !== null) {
                return $matching;
            }
        }

        return $query->value('id');
    }

    private function currentDriver(): ?Driver
    {
        $authId = auth()->id();
        if (! is_int($authId)) {
            return null;
        }

        return Driver::query()->where('user_id', $authId)->first();
    }
}
