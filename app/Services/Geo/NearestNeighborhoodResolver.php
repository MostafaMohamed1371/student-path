<?php

namespace App\Services\Geo;

use App\Models\Neighborhood;
use App\Support\GeoDistance;

/**
 * Resolve the nearest Iraq sub-district (neighborhood) for a GPS point.
 */
final class NearestNeighborhoodResolver
{
    public function resolveId(?float $latitude, ?float $longitude): ?int
    {
        $neighborhood = $this->resolve($latitude, $longitude);

        return $neighborhood !== null ? (int) $neighborhood->id : null;
    }

    public function resolve(?float $latitude, ?float $longitude): ?Neighborhood
    {
        if ($latitude === null || $longitude === null) {
            return null;
        }

        if ($latitude === 0.0 && $longitude === 0.0) {
            return null;
        }

        $maxRadiusKm = (float) config('routes.neighborhood_resolve_max_radius_km', 8);

        $best = null;
        $bestDistanceKm = PHP_FLOAT_MAX;

        foreach (Neighborhood::query()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get(['id', 'area_id', 'name', 'latitude', 'longitude']) as $neighborhood) {
            $distanceKm = GeoDistance::haversineKm(
                $latitude,
                $longitude,
                (float) $neighborhood->latitude,
                (float) $neighborhood->longitude,
            );

            if ($distanceKm < $bestDistanceKm) {
                $bestDistanceKm = $distanceKm;
                $best = $neighborhood;
            }
        }

        if ($best === null || $bestDistanceKm > $maxRadiusKm) {
            return null;
        }

        return $best;
    }
}
