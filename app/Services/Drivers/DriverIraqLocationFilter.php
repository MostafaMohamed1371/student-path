<?php

namespace App\Services\Drivers;

use App\Models\Area;
use App\Models\Driver;
use App\Models\Neighborhood;
use Illuminate\Database\Eloquent\Builder;

/**
 * Match drivers to Iraq location filters (governorate → district → sub-district).
 */
final class DriverIraqLocationFilter
{
    /**
     * @param  Builder<Driver>  $query
     */
    public function apply(Builder $query, int $governorateId, int $areaId, int $neighborhoodId): void
    {
        if ($neighborhoodId > 0) {
            $query->whereHas('neighborhoods', fn (Builder $relation) => $relation->whereKey($neighborhoodId));

            return;
        }

        if ($areaId > 0) {
            $this->applyAreaScope($query, $areaId);

            return;
        }

        if ($governorateId > 0) {
            $this->applyGovernorateScope($query, $governorateId);
        }
    }

    /**
     * @param  Builder<Driver>  $query
     */
    private function applyAreaScope(Builder $query, int $areaId): void
    {
        $neighborhoodIds = Neighborhood::query()->where('area_id', $areaId)->pluck('id');

        $query->where(function (Builder $q) use ($areaId, $neighborhoodIds): void {
            $q->where('area_id', $areaId);

            if ($neighborhoodIds->isNotEmpty()) {
                $q->orWhereHas('neighborhoods', fn (Builder $relation) => $relation->whereIn('neighborhoods.id', $neighborhoodIds));
            }
        });
    }

    /**
     * @param  Builder<Driver>  $query
     */
    private function applyGovernorateScope(Builder $query, int $governorateId): void
    {
        $areaIds = Area::query()->where('district_id', $governorateId)->pluck('id');

        $query->where(function (Builder $q) use ($governorateId, $areaIds): void {
            $q->where('district_id', $governorateId);

            if ($areaIds->isNotEmpty()) {
                $q->orWhereIn('area_id', $areaIds);

                $neighborhoodIds = Neighborhood::query()->whereIn('area_id', $areaIds)->pluck('id');
                if ($neighborhoodIds->isNotEmpty()) {
                    $q->orWhereHas('neighborhoods', fn (Builder $relation) => $relation->whereIn('neighborhoods.id', $neighborhoodIds));
                }
            }
        });
    }
}
