<?php

namespace App\Services\Trips;

use App\Contracts\Trips\TripLocationRepository;
use Illuminate\Support\Facades\Cache;

/**
 * Local / test store when Firebase Realtime Database is not configured.
 */
final class CacheTripLocationRepository implements TripLocationRepository
{
    public function write(int $tripHistoryId, array $tracking): void
    {
        $ttl = max(60, (int) config('trips.location_cache_ttl_seconds', 86400));
        Cache::put($this->cacheKey($tripHistoryId), $tracking, $ttl);
    }

    public function read(int $tripHistoryId): ?array
    {
        $data = Cache::get($this->cacheKey($tripHistoryId));

        return is_array($data) ? $data : null;
    }

    public function deactivate(int $tripHistoryId): void
    {
        $existing = $this->read($tripHistoryId);
        if (! is_array($existing)) {
            return;
        }

        $existing['active'] = false;
        $existing['deactivated_at'] = now()->toIso8601String();
        $this->write($tripHistoryId, $existing);
    }

    public function trackingPath(int $tripHistoryId): string
    {
        return TripLocationPath::forTrip($tripHistoryId);
    }

    private function cacheKey(int $tripHistoryId): string
    {
        return 'trip_location_tracking:'.$tripHistoryId;
    }
}
