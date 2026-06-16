<?php

namespace App\Http\Controllers\Web\Concerns;

use App\Models\Bus;
use App\Models\Driver;
use Illuminate\Database\Eloquent\Builder;

trait AssignsDriverBus
{
    protected function syncDriverBusAssignment(Driver $driver, ?int $busId): void
    {
        Bus::query()
            ->where('driver_id', $driver->id)
            ->where(function ($query) use ($busId): void {
                if ($busId !== null && $busId > 0) {
                    $query->where('id', '!=', $busId);
                }
            })
            ->update([
                'driver_id' => null,
                'user_id' => null,
            ]);

        if ($busId === null || $busId <= 0) {
            return;
        }

        Bus::query()
            ->whereKey($busId)
            ->where('school_id', $driver->school_id)
            ->where(function ($query) use ($driver): void {
                $query->whereNull('driver_id')
                    ->orWhere('driver_id', $driver->id);
            })
            ->update([
                'driver_id' => $driver->id,
                'user_id' => $driver->user_id,
            ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, Bus>
     */
    protected function busesForDriverForm(?int $schoolId, ?int $exceptDriverId = null)
    {
        if ($schoolId === null || $schoolId <= 0) {
            return collect();
        }

        return Bus::query()
            ->where(function (Builder $query) use ($schoolId): void {
                $query->where('school_id', $schoolId)
                    ->orWhereHas('driver', fn (Builder $driver) => $driver->where('school_id', $schoolId));
            })
            ->where(function ($query) use ($exceptDriverId): void {
                $query->whereNull('driver_id');
                if ($exceptDriverId !== null && $exceptDriverId > 0) {
                    $query->orWhere('driver_id', $exceptDriverId);
                }
            })
            ->orderBy('number')
            ->orderBy('name')
            ->get(['id', 'name', 'number', 'driver_id']);
    }
}
