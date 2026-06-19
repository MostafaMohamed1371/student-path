<?php

namespace App\Services\Drivers;

use App\Models\Driver;
use App\Models\TripHistory;
use Illuminate\Database\Eloquent\Builder;

final class DriverDeletionService
{
    public function deleteTripsForDriver(Driver $driver): void
    {
        $driverId = (int) $driver->id;
        if ($driverId <= 0) {
            return;
        }

        $ownedTripIds = TripHistory::query()
            ->where('driver_id', $driverId)
            ->pluck('id');

        if ($ownedTripIds->isEmpty()) {
            return;
        }

        $tripIdsToDelete = TripHistory::query()
            ->where(function (Builder $query) use ($driverId, $ownedTripIds): void {
                $query->where('driver_id', $driverId)
                    ->orWhereIn('recurring_template_id', $ownedTripIds);
            })
            ->pluck('id');

        if ($tripIdsToDelete->isEmpty()) {
            return;
        }

        TripHistory::query()->whereIn('id', $tripIdsToDelete)->delete();
    }
}
