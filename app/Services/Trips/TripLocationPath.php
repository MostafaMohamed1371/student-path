<?php

namespace App\Services\Trips;

final class TripLocationPath
{
    public static function forTrip(int $tripHistoryId): string
    {
        $template = (string) config('trips.location_firebase_path', 'trips/{tripId}/tracking');

        return str_replace('{tripId}', (string) $tripHistoryId, $template);
    }

    public static function locationChildPath(int $tripHistoryId): string
    {
        return self::forTrip($tripHistoryId).'/location';
    }
}
