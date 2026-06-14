<?php

namespace App\Services\Trips;

use App\Enums\TripType;
use App\Models\School;
use App\Models\TripHistory;
use Illuminate\Support\Collection;

final class TripRecurringScheduleService
{
    public function __construct(
        private readonly RecurringTripSpawner $spawner,
        private readonly PickupReturnTripPairPlanner $pairPlanner,
    ) {}

    /**
     * @param  iterable<TripHistory>  $trips
     */
    public function syncAfterStudentsAssigned(iterable $trips): int
    {
        $spawnedTotal = 0;

        foreach (Collection::make($trips)->unique(fn (TripHistory $trip): int => (int) $trip->id) as $trip) {
            $spawnedTotal += $this->syncTrip($trip);
        }

        return $spawnedTotal;
    }

    public function syncTrip(TripHistory $trip): int
    {
        $freshTrip = $trip->fresh(['school', 'tripHistoryStudents']);
        $this->syncAutoScheduleFlagOnPairedTrip($freshTrip);
        $freshTrip = $freshTrip->fresh(['school', 'tripHistoryStudents']);

        $hasStudents = $freshTrip->tripHistoryStudents->isNotEmpty()
            || (int) ($freshTrip->students_count ?? 0) > 0;

        if (! $hasStudents || ! $freshTrip->auto_schedule_work_days) {
            $this->spawner->unregisterTemplate($freshTrip);

            return 0;
        }

        $this->spawner->registerTemplateFromTrip($freshTrip);

        return $this->spawner->spawnAheadForTemplate(
            $freshTrip->fresh(['school', 'tripHistoryStudents']),
        );
    }

    public function enableAutoScheduleOnTripLegs(TripHistory $primary, ?TripHistory $paired = null): void
    {
        $primary->forceFill(['auto_schedule_work_days' => true])->save();

        if ($paired instanceof TripHistory) {
            $paired->forceFill(['auto_schedule_work_days' => true])->save();
        }
    }

    private function syncAutoScheduleFlagOnPairedTrip(TripHistory $trip): void
    {
        $school = $trip->school;
        if (! $school instanceof School) {
            $school = School::query()->find((int) $trip->school_id);
        }
        if (! $school instanceof School) {
            return;
        }

        $tripType = trim((string) ($trip->trip_type ?? ''));
        $paired = null;

        if (TripType::isPickup($tripType)) {
            $paired = $this->pairPlanner->findReturnTripForPickup($trip);
        } elseif (TripType::isReturn($tripType)) {
            $paired = $this->pairPlanner->findPickupTripForReturn($trip);
        }

        if ($paired instanceof TripHistory) {
            $paired->forceFill([
                'auto_schedule_work_days' => (bool) $trip->auto_schedule_work_days,
            ])->save();
        }
    }
}
