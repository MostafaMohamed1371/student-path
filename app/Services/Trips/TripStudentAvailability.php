<?php

namespace App\Services\Trips;

use App\Enums\TripType;
use App\Models\TripHistory;
use App\Models\TripHistoryStudent;
use Illuminate\Validation\ValidationException;

/**
 * Prevents the same student from being rostered on more than one open (ACTIVE) trip at a time.
 * Pickup and return legs for the same driver/day count as one assignment slot.
 */
final class TripStudentAvailability
{
    public function __construct(
        private readonly PickupReturnTripPairPlanner $pairPlanner,
    ) {}

    /**
     * @param  int|list<int>|null  $exceptTripIds
     * @return list<int>
     */
    public function studentIdsOnActiveTrips(int $schoolId, int|array|null $exceptTripIds = null): array
    {
        if ($schoolId <= 0) {
            return [];
        }

        $except = $this->expandExceptTripIdsWithPairs($exceptTripIds);

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
     * Pickup/return legs for the same driver and day share one roster slot.
     *
     * @param  int|list<int>|null  $exceptTripIds
     * @return list<int>
     */
    public function expandExceptTripIdsWithPairs(int|array|null $exceptTripIds): array
    {
        $ids = $this->normalizeExceptTripIds($exceptTripIds);
        if ($ids === []) {
            return [];
        }

        $expanded = $ids;
        $trips = TripHistory::query()
            ->whereIn('id', $ids)
            ->get(['id', 'driver_id', 'school_id', 'trip_type', 'start_time']);

        foreach ($trips as $trip) {
            $tripType = trim((string) ($trip->trip_type ?? ''));
            $paired = match (true) {
                TripType::isPickup($tripType) => $this->pairPlanner->findReturnTripForPickup($trip),
                TripType::isReturn($tripType) => $this->pairPlanner->findPickupTripForReturn($trip),
                default => null,
            };

            if ($paired instanceof TripHistory) {
                $expanded[] = (int) $paired->id;
            }
        }

        return $this->normalizeExceptTripIds($expanded);
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
