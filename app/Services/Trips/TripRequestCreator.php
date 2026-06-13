<?php

namespace App\Services\Trips;

use App\Models\Student;
use App\Models\TripRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class TripRequestCreator
{
    public function __construct(
        private readonly TripRequestSlotKeyResolver $slotKeyResolver,
        private readonly TripRequestConflictGuard $conflictGuard,
    ) {}

    /**
     * Create a pending trip request, or return an existing pending one for the same student + trip slot.
     *
     * A student may have separate pending requests for pickup and return (different trip types).
     *
     * @param  array<string, mixed>  $attributes  trip_history_id, status, notes, present_type, moving_point, stop_point, subscribe_price
     * @return array{0: TripRequest, 1: bool}  trip request and whether a new row was created
     */
    public function createOrReturnExistingPending(
        User $user,
        Student $student,
        ?int $driverId,
        array $attributes,
    ): array {
        return DB::transaction(function () use ($user, $student, $driverId, $attributes): array {
            Student::query()->whereKey($student->id)->lockForUpdate()->first();

            $tripHistoryId = isset($attributes['trip_history_id'])
                ? (int) $attributes['trip_history_id']
                : null;
            $slotKey = $this->slotKeyResolver->slotKeyForNewRequest(
                $tripHistoryId > 0 ? $tripHistoryId : null,
                $attributes,
            );

            $this->conflictGuard->assertCanCreatePendingRequest($student, $slotKey, $attributes);

            $existing = $this->conflictGuard->findPendingRequestForParentStudentDriverSlot(
                (int) $user->id,
                (int) $student->id,
                $driverId,
                $slotKey,
            );
            if ($existing instanceof TripRequest) {
                return [$existing, false];
            }

            $row = TripRequest::query()->create([
                'user_id' => $user->id,
                'student_id' => $student->id,
                'driver_id' => $driverId,
                ...$attributes,
            ]);

            app(TripRequestNotificationService::class)->notifyDriverOfNewPendingRequest($row);

            return [$row, true];
        });
    }
}
