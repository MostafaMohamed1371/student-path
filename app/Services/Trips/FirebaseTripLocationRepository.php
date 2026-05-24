<?php

namespace App\Services\Trips;

use App\Contracts\Trips\TripLocationRepository;
use Illuminate\Support\Facades\Log;
use Kreait\Laravel\Firebase\Facades\Firebase;
use Throwable;

final class FirebaseTripLocationRepository implements TripLocationRepository
{
    public function write(int $tripHistoryId, array $tracking): void
    {
        $this->reference($tripHistoryId)->set($tracking);
    }

    public function read(int $tripHistoryId): ?array
    {
        try {
            $value = $this->reference($tripHistoryId)->getValue();
        } catch (Throwable $e) {
            Log::warning('Firebase trip location read failed', [
                'trip_history_id' => $tripHistoryId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        return is_array($value) ? $value : null;
    }

    public function deactivate(int $tripHistoryId): void
    {
        try {
            $this->reference($tripHistoryId)->update([
                'active' => false,
                'deactivated_at' => now()->toIso8601String(),
            ]);
        } catch (Throwable $e) {
            Log::warning('Firebase trip location deactivate failed', [
                'trip_history_id' => $tripHistoryId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function trackingPath(int $tripHistoryId): string
    {
        return TripLocationPath::forTrip($tripHistoryId);
    }

    private function reference(int $tripHistoryId): \Kreait\Firebase\Database\Reference
    {
        return Firebase::database()->getReference($this->trackingPath($tripHistoryId));
    }
}
