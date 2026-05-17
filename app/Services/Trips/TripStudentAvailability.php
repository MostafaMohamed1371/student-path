<?php

namespace App\Services\Trips;

use App\Models\TripHistoryStudent;
use Illuminate\Validation\ValidationException;

/**
 * Prevents the same student from being rostered on more than one open (ACTIVE) trip at a time.
 */
final class TripStudentAvailability
{
    /**
     * @return list<int>
     */
    public function studentIdsOnActiveTrips(int $schoolId, ?int $exceptTripId = null): array
    {
        if ($schoolId <= 0) {
            return [];
        }

        $query = TripHistoryStudent::query()
            ->join('trip_histories', 'trip_histories.id', '=', 'trip_history_students.trip_history_id')
            ->where('trip_histories.school_id', $schoolId)
            ->where('trip_histories.status', 'ACTIVE');

        if ($exceptTripId !== null && $exceptTripId > 0) {
            $query->where('trip_histories.id', '!=', $exceptTripId);
        }

        return $query
            ->distinct()
            ->pluck('trip_history_students.student_id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @param  list<int|string>  $studentIds
     */
    public function assertStudentsAvailableForTrip(array $studentIds, int $schoolId, ?int $exceptTripId = null): void
    {
        $unique = array_values(array_unique(array_map(static fn ($v): int => (int) $v, $studentIds)));
        if ($unique === []) {
            return;
        }

        $booked = $this->studentIdsOnActiveTrips($schoolId, $exceptTripId);
        $conflicts = array_values(array_intersect($unique, $booked));
        if ($conflicts === []) {
            return;
        }

        throw ValidationException::withMessages([
            'student_ids' => [__('dashboard.trip_students_already_assigned')],
        ]);
    }
}
