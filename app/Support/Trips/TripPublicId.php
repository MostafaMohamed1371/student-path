<?php

namespace App\Support\Trips;

use App\Models\TripHistory;

final class TripPublicId
{
    public static function parseTrip(string $raw): ?int
    {
        $raw = trim($raw);
        if (preg_match('/^TRP-(\d+)$/i', $raw, $m)) {
            return (int) $m[1];
        }
        if (preg_match('/^\d+$/', $raw)) {
            return (int) $raw;
        }

        return null;
    }

    public static function forTrip(TripHistory|int $trip): string
    {
        $id = $trip instanceof TripHistory ? (int) $trip->id : $trip;

        return 'TRP-'.$id;
    }

    public static function forStudent(int $studentId): string
    {
        return 'ST-'.str_pad((string) $studentId, 3, '0', STR_PAD_LEFT);
    }
}
