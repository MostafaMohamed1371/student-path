<?php

namespace App\Support;

/**
 * Great-circle distance on a spherical Earth (WGS84-style usage for short ranges).
 */
final class GeoDistance
{
    private const float EARTH_RADIUS_KM = 6371.0;

    public static function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $φ1 = deg2rad($lat1);
        $φ2 = deg2rad($lat2);
        $Δφ = deg2rad($lat2 - $lat1);
        $Δλ = deg2rad($lon2 - $lon1);

        $a = sin($Δφ / 2) ** 2 + cos($φ1) * cos($φ2) * sin($Δλ / 2) ** 2;

        return self::EARTH_RADIUS_KM * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
