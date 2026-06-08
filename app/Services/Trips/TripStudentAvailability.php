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
     * @param  int|list<int>|null  $exceptTripIds
     * @return list<int>
     */
    public function studentIdsOnActiveTrips(int $schoolId, int|array|null $exceptTripIds = null): array
    {
        if ($schoolId <= 0) {
            return [];
        }

        $except = $this->normalizeExceptTripIds($exceptTripIds);

        $query = TripHistoryStudent::query()
            ->join('trip_histories', 'trip_histories.id', '=', 'trip_history_students.trip_history_id')
            ->where('trip_histories.school_id', $schoolId)
            ->where('trip_histories.status', 'ACTIVE');

        if ($except !== []) {
            $query->whereNotIn('trip_histories.id', $except);
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
     * @param  int|list<int>|null  $exceptTripIds
     */
    public function assertStudentsAvailableForTrip(array $studentIds, int $schoolId, int|array|null $exceptTripIds = null): void
    {
        $unique = array_values(array_unique(array_map(static fn ($v): int => (int) $v, $studentIds)));
        if ($unique === []) {
            return;
        }

        $booked = $this->studentIdsOnActiveTrips($schoolId, $exceptTripIds);
        $conflicts = array_values(array_intersect($unique, $booked));
        if ($conflicts === []) {
            return;
        }

        throw ValidationException::withMessages([
            'student_ids' => [__('dashboard.trip_students_already_assigned')],
        ]);
    }

    /**
     * @param  int|list<int>|null  $exceptTripIds
     * @return list<int>
     */
    private function normalizeExceptTripIds(int|array|null $exceptTripIds): array
    {
        if ($exceptTripIds === null) {
            return [];
        }

        $ids = is_array($exceptTripIds) ? $exceptTripIds : [$exceptTripIds];

        return array_values(array_unique(array_filter(
            array_map(static fn ($v): int => (int) $v, $ids),
            static fn (int $id): bool => $id > 0,
        )));
    }
}
