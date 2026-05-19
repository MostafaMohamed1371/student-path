<?php

namespace App\Services\Trips;

use App\Models\Student;
use App\Models\TripRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class TripRequestCreator
{
    /**
     * Create a pending trip request, or return an existing pending one for the same parent + student.
     *
     * Prevents duplicate rows when the mobile app double-submits (double tap / retry).
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

            $existing = TripRequest::query()
                ->where('user_id', $user->id)
                ->where('student_id', $student->id)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if ($existing instanceof TripRequest) {
                return [$existing, false];
            }

            $row = TripRequest::query()->create([
                'user_id' => $user->id,
                'student_id' => $student->id,
                'driver_id' => $driverId,
                ...$attributes,
            ]);

            return [$row, true];
        });
    }
}
