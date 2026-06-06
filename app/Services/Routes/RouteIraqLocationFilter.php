<?php

namespace App\Services\Routes;

use App\Models\Area;
use App\Models\Neighborhood;
use App\Models\TransportRoute;
use Illuminate\Database\Eloquent\Builder;

/**
 * Match transport routes to Iraq location filters (governorate → district → sub-district).
 *
 * Governorate = districts table, district = areas, sub-district = neighborhoods.
 */
final class RouteIraqLocationFilter
{
    public function apply(Builder $query, int $governorateId, int $areaId, int $neighborhoodId): void
    {
        if ($neighborhoodId > 0) {
            $this->applyNeighborhoodScope($query, $neighborhoodId);

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
     * @param  Builder<TransportRoute>  $query
     */
    private function applyNeighborhoodScope(Builder $query, int $neighborhoodId): void
    {
        $neighborhood = Neighborhood::query()->find($neighborhoodId);
        $maxMeters = $this->maxMeters();

        $query->where(function (Builder $q) use ($neighborhoodId, $neighborhood, $maxMeters): void {
            $q->whereHas('neighborhoods', fn (Builder $relation) => $relation->whereKey($neighborhoodId));

            if ($neighborhood && $neighborhood->latitude !== null && $neighborhood->longitude !== null) {
                $this->orWhereUntaggedStartNearPoint(
                    $q,
                    (float) $neighborhood->latitude,
                    (float) $neighborhood->longitude,
                    $maxMeters,
                );
            }
        });
    }

    /**
     * @param  Builder<TransportRoute>  $query
     */
    private function applyAreaScope(Builder $query, int $areaId): void
    {
        $maxMeters = $this->maxMeters();
        $neighborhoodIds = Neighborhood::query()->where('area_id', $areaId)->pluck('id');

        $query->where(function (Builder $q) use ($areaId, $neighborhoodIds, $maxMeters): void {
            $q->where('area_id', $areaId);

            if ($neighborhoodIds->isNotEmpty()) {
                $q->orWhereHas('neighborhoods', fn (Builder $relation) => $relation->whereIn('neighborhoods.id', $neighborhoodIds));
            }

            $this->orWhereStartNearNeighborhoodsInArea($q, $areaId, $maxMeters);
        });
    }

    /**
     * @param  Builder<TransportRoute>  $query
     */
    private function applyGovernorateScope(Builder $query, int $governorateId): void
    {
        $areaIds = Area::query()->where('district_id', $governorateId)->pluck('id');
        $maxMeters = $this->maxMeters();

        $query->where(function (Builder $q) use ($governorateId, $areaIds, $maxMeters): void {
            $q->where('district_id', $governorateId);

            if ($areaIds->isNotEmpty()) {
                $q->orWhereIn('area_id', $areaIds);

                $neighborhoodIds = Neighborhood::query()->whereIn('area_id', $areaIds)->pluck('id');
                if ($neighborhoodIds->isNotEmpty()) {
                    $q->orWhereHas('neighborhoods', fn (Builder $relation) => $relation->whereIn('neighborhoods.id', $neighborhoodIds));
                }

                $this->orWhereStartNearNeighborhoodsInAreas($q, $areaIds, $maxMeters);
            }
        });
    }

    /**
     * @param  Builder<TransportRoute>  $query
     */
    private function orWhereStartNearNeighborhoodsInArea(Builder $query, int $areaId, float $maxMeters): void
    {
        $neighborhoods = Neighborhood::query()
            ->where('area_id', $areaId)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get(['latitude', 'longitude']);

        if ($neighborhoods->isEmpty()) {
            return;
        }

        $this->orWhereUntaggedStartNearAnyNeighborhood($query, $neighborhoods, $maxMeters);
    }

    /**
     * @param  Builder<TransportRoute>  $query
     * @param  \Illuminate\Support\Collection<int, int>  $areaIds
     */
    private function orWhereStartNearNeighborhoodsInAreas(Builder $query, $areaIds, float $maxMeters): void
    {
        $neighborhoods = Neighborhood::query()
            ->whereIn('area_id', $areaIds)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get(['latitude', 'longitude']);

        if ($neighborhoods->isEmpty()) {
            return;
        }

        $this->orWhereUntaggedStartNearAnyNeighborhood($query, $neighborhoods, $maxMeters);
    }

    /**
     * @param  Builder<TransportRoute>  $query
     * @param  \Illuminate\Support\Collection<int, Neighborhood>|\Illuminate\Support\Collection<int, object{latitude: float, longitude: float}>  $neighborhoods
     */
    private function orWhereUntaggedStartNearAnyNeighborhood(Builder $query, $neighborhoods, float $maxMeters): void
    {
        if ($neighborhoods->isEmpty()) {
            return;
        }

        $query->orWhere(function (Builder $geo) use ($neighborhoods, $maxMeters): void {
            $geo->whereNull('district_id')
                ->whereNull('area_id')
                ->whereDoesntHave('neighborhoods')
                ->where(function (Builder $near) use ($neighborhoods, $maxMeters): void {
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
        });
    }

    /**
     * @param  Builder<TransportRoute>  $query
     */
    private function orWhereUntaggedStartNearPoint(Builder $query, float $lat, float $lng, float $maxMeters): void
    {
        $query->orWhere(function (Builder $geo) use ($lat, $lng, $maxMeters): void {
            $geo->whereNull('district_id')
                ->whereNull('area_id')
                ->whereDoesntHave('neighborhoods')
                ->where(function (Builder $q) use ($lat, $lng, $maxMeters): void {
                    $this->whereStartNearPoint($q, $lat, $lng, $maxMeters);
                });
        });
    }

    /**
     * @param  Builder<TransportRoute>  $query
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
