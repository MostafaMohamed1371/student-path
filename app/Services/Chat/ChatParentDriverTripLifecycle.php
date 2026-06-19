<?php

namespace App\Services\Chat;

use App\Models\ChatConversation;
use App\Models\TripHistory;
use App\Models\TripRequest;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Parent ↔ driver chat is tied to an active trip:
 * opens when the driver starts the trip (for parents on that trip),
 * closes when the trip is completed.
 */
final class ChatParentDriverTripLifecycle
{
    public function __construct(
        private readonly ChatParentDriverConversationProvisioner $provisioner,
    ) {}

    public function openForStartedTrip(TripHistory $trip): void
    {
        $trip->loadMissing(['driver.user', 'tripHistoryStudents.student']);

        $driverUserId = (int) ($trip->driver?->user_id ?? 0);
        if ($driverUserId <= 0) {
            return;
        }

        $schoolId = (int) ($trip->school_id ?? 0);

        foreach ($this->parentContextsForTrip($trip) as $context) {
            $parentUserId = (int) ($context['parent_user_id'] ?? 0);
            if ($parentUserId <= 0) {
                continue;
            }

            $this->provisioner->ensureBetweenParentAndDriver(
                $parentUserId,
                $driverUserId,
                $schoolId > 0 ? $schoolId : null,
                $context['trip_request_id'] ?? null,
                (int) $trip->id,
            );
        }
    }

    public function closeForCompletedTrip(TripHistory $trip): void
    {
        ChatConversation::query()
            ->where('conversation_type', ChatConversation::TYPE_PARENT_DRIVER)
            ->where('status', 'open')
            ->whereNull('deleted_at')
            ->where('trip_history_id', (int) $trip->id)
            ->update(['status' => 'closed']);
    }

    /**
     * @return list<array{parent_user_id: int, trip_request_id: int|null}>
     */
    private function parentContextsForTrip(TripHistory $trip): array
    {
        $tripRequestByStudent = TripRequest::query()
            ->where('trip_history_id', (int) $trip->id)
            ->where('status', 'accepted')
            ->get(['id', 'user_id', 'student_id'])
            ->keyBy('student_id');

        $contexts = [];

        foreach ($this->parentUsersForTrip($trip) as $parentUser) {
            $studentId = (int) ($parentUser->getAttribute('matched_student_id') ?? 0);
            $tripRequest = $studentId > 0 ? $tripRequestByStudent->get($studentId) : null;

            $contexts[] = [
                'parent_user_id' => (int) $parentUser->id,
                'trip_request_id' => $tripRequest !== null ? (int) $tripRequest->id : null,
            ];
        }

        return $contexts;
    }

    /**
     * @return Collection<int, User>
     */
    private function parentUsersForTrip(TripHistory $trip): Collection
    {
        $users = collect();

        foreach ($trip->tripHistoryStudents as $pivot) {
            $student = $pivot->student;
            if ($student === null || ! $student->guardian_id) {
                continue;
            }

            $parentUser = User::query()->where('guardian_id', (int) $student->guardian_id)->first();
            if (! $parentUser instanceof User) {
                continue;
            }

            $parentUser->setAttribute('matched_student_id', (int) $student->id);
            $users->put((int) $parentUser->id, $parentUser);
        }

        return $users->values();
    }
}
