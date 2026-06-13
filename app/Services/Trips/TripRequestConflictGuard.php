<?php

namespace App\Services\Trips;

use App\Models\Student;
use App\Models\TripRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class TripRequestConflictGuard
{
    public function __construct(
        private readonly TripRequestSlotKeyResolver $slotKeyResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     *
     * @throws ValidationException
     */
    public function assertCanCreatePendingRequest(Student $student, ?string $slotKey, array $attributes): void
    {
        if ($slotKey === null) {
            return;
        }

        if ($this->hasAcceptedRequestForSlotToday((int) $student->id, $slotKey)) {
            throw ValidationException::withMessages([
                'student_id' => [__('dashboard.trip_request_slot_already_accepted')],
            ]);
        }
    }

    public function findPendingRequestForSlot(int $studentId, ?string $slotKey): ?TripRequest
    {
        if ($slotKey === null) {
            return null;
        }

        return $this->pendingRequestsForStudent($studentId)
            ->first(fn (TripRequest $request): bool => $this->slotKeyResolver->slotKeyForRequest($request) === $slotKey);
    }

    public function findPendingRequestForParentStudentDriverSlot(
        int $userId,
        int $studentId,
        ?int $driverId,
        ?string $slotKey,
    ): ?TripRequest {
        return TripRequest::query()
            ->where('user_id', $userId)
            ->where('student_id', $studentId)
            ->where('driver_id', $driverId)
            ->where('status', 'pending')
            ->with('tripHistory')
            ->latest('id')
            ->get()
            ->first(function (TripRequest $request) use ($slotKey): bool {
                if ($slotKey === null) {
                    return true;
                }

                return $this->slotKeyResolver->slotKeyForRequest($request) === $slotKey;
            });
    }

    /**
     * @throws ValidationException
     */
    public function assertCanAcceptRequest(TripRequest $request, ?string $slotKey = null): void
    {
        if ($this->slotTakenByAnotherDriver($request, $slotKey)) {
            throw ValidationException::withMessages([
                'status' => [__('dashboard.trip_request_slot_taken_by_another_driver')],
            ]);
        }
    }

    public function closePendingRequestWhenSlotTaken(TripRequest $request, ?string $slotKey = null): bool
    {
        if ($request->status !== 'pending') {
            return false;
        }

        if (! $this->slotTakenByAnotherDriver($request, $slotKey)) {
            return false;
        }

        $request->update(['status' => 'rejected']);

        return true;
    }

    public function closeStalePendingRequestsForDriver(int $driverId): void
    {
        TripRequest::query()
            ->where('driver_id', $driverId)
            ->where('status', 'pending')
            ->with('tripHistory')
            ->orderBy('id')
            ->get()
            ->each(fn (TripRequest $request): bool => $this->closePendingRequestWhenSlotTaken($request));
    }

    public function slotTakenByAnotherDriver(TripRequest $request, ?string $slotKey = null): bool
    {
        if ($request->student_id === null) {
            return false;
        }

        $slotKey ??= $this->slotKeyResolver->slotKeyForRequest($request);
        if ($slotKey === null) {
            return false;
        }

        return $this->findAcceptedRequestForSlotToday(
            (int) $request->student_id,
            $slotKey,
            (int) $request->id,
        ) instanceof TripRequest;
    }

    public function rejectCompetingPendingRequests(TripRequest $accepted, ?string $slotKey = null): void
    {
        if ($accepted->student_id === null) {
            return;
        }

        $slotKey ??= $this->slotKeyResolver->slotKeyForRequest($accepted);
        if ($slotKey === null) {
            return;
        }

        $competingIds = $this->pendingRequestsForStudent((int) $accepted->student_id)
            ->filter(fn (TripRequest $request): bool => (int) $request->id !== (int) $accepted->id
                && $this->slotKeyResolver->slotKeyForRequest($request) === $slotKey)
            ->pluck('id')
            ->all();

        if ($competingIds === []) {
            return;
        }

        TripRequest::query()
            ->whereIn('id', $competingIds)
            ->lockForUpdate()
            ->get()
            ->each(function (TripRequest $request): void {
                if ($request->status === 'pending') {
                    $request->update(['status' => 'rejected']);
                }
            });
    }

    private function hasAcceptedRequestForSlotToday(int $studentId, string $slotKey, ?int $exceptRequestId = null): bool
    {
        return $this->findAcceptedRequestForSlotToday($studentId, $slotKey, $exceptRequestId) instanceof TripRequest;
    }

    private function findAcceptedRequestForSlotToday(
        int $studentId,
        string $slotKey,
        ?int $exceptRequestId = null,
    ): ?TripRequest {
        return TripRequest::query()
            ->where('student_id', $studentId)
            ->where('status', 'accepted')
            ->whereDate('updated_at', now()->toDateString())
            ->when($exceptRequestId !== null, fn ($q) => $q->where('id', '!=', $exceptRequestId))
            ->with('tripHistory')
            ->get()
            ->first(fn (TripRequest $request): bool => $this->slotKeyResolver->slotKeyForRequest($request) === $slotKey);
    }

    /**
     * @return Collection<int, TripRequest>
     */
    private function pendingRequestsForStudent(int $studentId): Collection
    {
        return TripRequest::query()
            ->where('student_id', $studentId)
            ->where('status', 'pending')
            ->with('tripHistory')
            ->orderByDesc('id')
            ->get();
    }
}
