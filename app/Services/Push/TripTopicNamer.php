<?php

namespace App\Services\Push;

use App\Models\TripHistory;

final class TripTopicNamer
{
    public function topicForTrip(TripHistory|int $trip): string
    {
        $tripId = $trip instanceof TripHistory ? (int) $trip->id : $trip;
        $prefix = (string) config('realtime.channel_prefix', 'trip_');

        return $prefix.$tripId;
    }

    public function tripIdFromTopic(string $topic): ?int
    {
        $prefix = (string) config('realtime.channel_prefix', 'trip_');
        if (! str_starts_with($topic, $prefix)) {
            return null;
        }

        $id = substr($topic, strlen($prefix));
        if ($id === '' || ! ctype_digit($id)) {
            return null;
        }

        return (int) $id;
    }
}
