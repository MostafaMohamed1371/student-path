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
            ->where(function (Builder $query) use ($driver): void {
                $query->where('school_id', $driver->school_id)
                    ->orWhereHas('driver', fn (Builder $d) => $d->where('school_id', $driver->school_id));
            })
            ->update([
                'driver_id' => $driver->id,
                'user_id' => $driver->user_id,
            ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, Bus>
     */
    protected function busesForDriverForm(?int $schoolId)
    {
        if ($schoolId === null || $schoolId <= 0) {
            return collect();
        }

        return Bus::query()
            ->with('driver:id,first_name,father_name,last_name,school_id')
            ->where(function (Builder $query) use ($schoolId): void {
                $query->where('school_id', $schoolId)
                    ->orWhereHas('driver', fn (Builder $driver) => $driver->where('school_id', $schoolId));
            })
            ->orderBy('number')
            ->orderBy('name')
            ->get(['id', 'name', 'number', 'driver_id', 'school_id']);
    }

    protected function busOptionLabelForDriverForm(Bus $bus): string
    {
        return $bus->driverFormOptionLabel();
    }
}
