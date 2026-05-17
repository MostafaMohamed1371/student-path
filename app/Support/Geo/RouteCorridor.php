<?php

namespace App\Support\Geo;

final class RouteCorridor
{
    private const EARTH_RADIUS_M = 6371000.0;

    /**
     * Distance from a point to the line segment A→B, and projection along the segment.
     *
     * @return array{distance_meters: float, projection_t: float}
     */
    public static function pointToSegment(
        float $pointLat,
        float $pointLng,
        float $startLat,
        float $startLng,
        float $endLat,
        float $endLng,
    ): array {
        $midLatRad = deg2rad(($startLat + $endLat + $pointLat) / 3);
        $scaleLng = cos($midLatRad);

        $px = self::lngToMeters($pointLng - $startLng, $scaleLng);
        $py = self::latToMeters($pointLat - $startLat);
        $bx = self::lngToMeters($endLng - $startLng, $scaleLng);
        $by = self::latToMeters($endLat - $startLat);

        $segmentLenSq = $bx * $bx + $by * $by;

        if ($segmentLenSq < 1e-6) {
            return [
                'distance_meters' => Haversine::metersBetween($pointLat, $pointLng, $startLat, $startLng),
                'projection_t' => 0.0,
            ];
        }

        $t = ($px * $bx + $py * $by) / $segmentLenSq;
        $tClamped = max(0.0, min(1.0, $t));

        $closestLat = $startLat + self::metersToLat($by * $tClamped);
        $closestLng = $startLng + self::metersToLng($bx * $tClamped, $scaleLng);

        return [
            'distance_meters' => Haversine::metersBetween($pointLat, $pointLng, $closestLat, $closestLng),
            'projection_t' => $tClamped,
        ];
    }

    public static function isOnCorridor(
        float $pointLat,
        float $pointLng,
        float $startLat,
        float $startLng,
        float $endLat,
        float $endLng,
        float $maxDistanceMeters,
    ): bool {
        $result = self::pointToSegment($pointLat, $pointLng, $startLat, $startLng, $endLat, $endLng);

        return $result['distance_meters'] <= $maxDistanceMeters;
    }

    private static function latToMeters(float $deltaLat): float
    {
        return deg2rad($deltaLat) * self::EARTH_RADIUS_M;
    }

    private static function lngToMeters(float $deltaLng, float $scaleLng): float
    {
        return deg2rad($deltaLng) * self::EARTH_RADIUS_M * $scaleLng;
    }

    private static function metersToLat(float $meters): float
    {
        return rad2deg($meters / self::EARTH_RADIUS_M);
    }

    private static function metersToLng(float $meters, float $scaleLng): float
    {
        if (abs($scaleLng) < 1e-9) {
            return 0.0;
        }

        return rad2deg($meters / (self::EARTH_RADIUS_M * $scaleLng));
    }
}
