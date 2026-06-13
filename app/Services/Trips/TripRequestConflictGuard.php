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
    public function assertCanAcceptRequest(TripRequest $request): void
    {
        $slotKey = $this->slotKeyResolver->slotKeyForRequest($request);
        if ($slotKey === null || $request->student_id === null) {
            return;
        }

        if ($this->hasAcceptedRequestForSlotToday((int) $request->student_id, $slotKey)) {
            throw ValidationException::withMessages([
                'status' => [__('dashboard.trip_request_slot_already_accepted')],
            ]);
        }
    }

    public function rejectCompetingPendingRequests(TripRequest $accepted): void
    {
        $slotKey = $this->slotKeyResolver->slotKeyForRequest($accepted);
        if ($slotKey === null || $accepted->student_id === null) {
            return;
        }

        $this->pendingRequestsForStudent((int) $accepted->student_id)
            ->filter(fn (TripRequest $request): bool => (int) $request->id !== (int) $accepted->id
                && $this->slotKeyResolver->slotKeyForRequest($request) === $slotKey)
            ->each(function (TripRequest $request): void {
                $request->update(['status' => 'rejected']);
            });
    }

    private function hasAcceptedRequestForSlotToday(int $studentId, string $slotKey): bool
    {
        return TripRequest::query()
            ->where('student_id', $studentId)
            ->where('status', 'accepted')
            ->whereDate('updated_at', now()->toDateString())
            ->with('tripHistory')
            ->get()
            ->contains(fn (TripRequest $request): bool => $this->slotKeyResolver->slotKeyForRequest($request) === $slotKey);
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
