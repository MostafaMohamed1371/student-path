<?php

namespace App\Services\Trips;

use App\Models\Area;
use App\Models\Neighborhood;
use App\Models\TripHistory;
use App\Services\Drivers\DriverIraqLocationFilter;
use Illuminate\Database\Eloquent\Builder;

/**
 * Match trip histories to Iraq location filters (governorate → district → sub-district).
 *
 * Matches when the assigned driver is in scope, or when the trip start point is near
 * neighborhoods in the selected location.
 */
final class TripIraqLocationFilter
{
    public function __construct(
        private readonly DriverIraqLocationFilter $driverIraqLocationFilter,
    ) {}

    /**
     * @param  Builder<TripHistory>  $query
     */
    public function apply(Builder $query, int $governorateId, int $areaId, int $neighborhoodId): void
    {
        if ($neighborhoodId === 0 && $areaId === 0 && $governorateId === 0) {
            return;
        }

        $query->where(function (Builder $q) use ($governorateId, $areaId, $neighborhoodId): void {
            $q->whereHas('driver', function (Builder $driverQuery) use ($governorateId, $areaId, $neighborhoodId): void {
                $this->driverIraqLocationFilter->apply($driverQuery, $governorateId, $areaId, $neighborhoodId);
            });

            if ($neighborhoodId > 0) {
                $this->orWhereStartNearNeighborhood($q, $neighborhoodId);

                return;
            }

            if ($areaId > 0) {
                $this->orWhereStartNearNeighborhoodsInArea($q, $areaId);

                return;
            }

            if ($governorateId > 0) {
                $areaIds = Area::query()->where('district_id', $governorateId)->pluck('id');
                if ($areaIds->isNotEmpty()) {
                    $this->orWhereStartNearNeighborhoodsInAreas($q, $areaIds);
                }
            }
        });
    }

    /**
     * @param  Builder<TripHistory>  $query
     */
    private function orWhereStartNearNeighborhood(Builder $query, int $neighborhoodId): void
    {
        $neighborhood = Neighborhood::query()->find($neighborhoodId);
        if ($neighborhood === null || $neighborhood->latitude === null || $neighborhood->longitude === null) {
            return;
        }

        $maxMeters = $this->maxMeters();
        $query->orWhere(function (Builder $q) use ($neighborhood, $maxMeters): void {
            $this->whereStartNearPoint(
                $q,
                (float) $neighborhood->latitude,
                (float) $neighborhood->longitude,
                $maxMeters,
            );
        });
    }

    /**
     * @param  Builder<TripHistory>  $query
     */
    private function orWhereStartNearNeighborhoodsInArea(Builder $query, int $areaId): void
    {
        $neighborhoods = Neighborhood::query()
            ->where('area_id', $areaId)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get(['latitude', 'longitude']);

        $this->orWhereStartNearAnyNeighborhood($query, $neighborhoods);
    }

    /**
     * @param  Builder<TripHistory>  $query
     * @param  \Illuminate\Support\Collection<int, int>  $areaIds
     */
    private function orWhereStartNearNeighborhoodsInAreas(Builder $query, $areaIds): void
    {
        $neighborhoods = Neighborhood::query()
            ->whereIn('area_id', $areaIds)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get(['latitude', 'longitude']);

        $this->orWhereStartNearAnyNeighborhood($query, $neighborhoods);
    }

    /**
     * @param  Builder<TripHistory>  $query
     * @param  \Illuminate\Support\Collection<int, Neighborhood>|\Illuminate\Support\Collection<int, object{latitude: float, longitude: float}>  $neighborhoods
     */
    private function orWhereStartNearAnyNeighborhood(Builder $query, $neighborhoods): void
    {
        if ($neighborhoods->isEmpty()) {
            return;
        }

        $maxMeters = $this->maxMeters();
        $query->orWhere(function (Builder $near) use ($neighborhoods, $maxMeters): void {
            $first = true;
            foreach ($neighborhoods as $neighborhood) {
                $lat = (float) $neighborhood->latitude;
                $lng = (float) $neighborhood->longitude;
                if ($first) {
                    $near->where(function (Builder $q) use ($lat, $lng, $maxMeters): void {
                        $this->whereStartNearPoint($q, $lat, $lng, $maxMeters);
                    });
                    $first = false;

                    continue;
                }

                $near->orWhere(function (Builder $q) use ($lat, $lng, $maxMeters): void {
                    $this->whereStartNearPoint($q, $lat, $lng, $maxMeters);
                });
            }
        });
    }

    /**
     * @param  Builder<TripHistory>  $query
     */
    private function whereStartNearPoint(Builder $query, float $lat, float $lng, float $maxMeters): void
    {
        $query->whereNotNull('start_latitude')
            ->whereNotNull('start_longitude')
            ->whereRaw($this->haversineWithinSql('start_latitude', 'start_longitude'), [
                $lat,
                $lat,
                $lng,
                $maxMeters,
            ]);
    }

    private function haversineWithinSql(string $latColumn, string $lngColumn): string
    {
        return '(6371000 * 2 * ASIN(SQRT(
            POWER(SIN(RADIANS('.$latColumn.' - ?) / 2), 2)
            + COS(RADIANS(?)) * COS(RADIANS('.$latColumn.'))
            * POWER(SIN(RADIANS('.$lngColumn.' - ?) / 2), 2)
        ))) <= ?';
    }

    private function maxMeters(): float
    {
        return (float) config('routes.location_filter_max_meters', 5000);
    }
}
