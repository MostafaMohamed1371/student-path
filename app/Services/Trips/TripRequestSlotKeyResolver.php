<?php

namespace App\Services\Trips;

use App\Enums\TripType;
use App\Models\TripHistory;
use App\Models\TripRequest;

/**
 * One student may have separate trip requests per trip type per day
 * (e.g. MORNING_PICKUP and MORNING_RETURN), but not two competing requests
 * for the same type.
 */
final class TripRequestSlotKeyResolver
{
    public function slotKeyForTripHistoryId(?int $tripHistoryId): ?string
    {
        if ($tripHistoryId === null || $tripHistoryId <= 0) {
            return null;
        }

        $tripType = TripHistory::query()->whereKey($tripHistoryId)->value('trip_type');

        return $this->normalizeTripType($tripType);
    }

    public function slotKeyForRequest(TripRequest $request): ?string
    {
        $request->loadMissing('tripHistory');

        $fromTrip = $this->normalizeTripType($request->tripHistory?->trip_type);
        if ($fromTrip !== null) {
            return $fromTrip;
        }

        return $this->inferTripTypeFromPresentType($request->present_type);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function slotKeyForNewRequest(?int $tripHistoryId, array $attributes): ?string
    {
        $fromTrip = $this->slotKeyForTripHistoryId($tripHistoryId);
        if ($fromTrip !== null) {
            return $fromTrip;
        }

        $presentType = isset($attributes['present_type']) ? (string) $attributes['present_type'] : null;

        return $this->inferTripTypeFromPresentType($presentType);
    }

    public function inferTripTypeFromPresentType(?string $presentType): ?string
    {
        if (! is_string($presentType) || trim($presentType) === '') {
            return null;
        }

        $t = mb_strtolower(trim($presentType));
        $isMorning = str_contains($t, 'صباح');
        $isEvening = str_contains($t, 'مساء') || str_contains($t, 'مسائي');
        $isReturn = str_contains($t, 'عود')
            || str_contains($t, 'return')
            || str_contains($t, 'رجوع');

        if ($isMorning) {
            return $isReturn ? TripType::MORNING_RETURN->value : TripType::MORNING_PICKUP->value;
        }

        if ($isEvening) {
            return $isReturn ? TripType::EVENING_RETURN->value : TripType::EVENING_PICKUP->value;
        }

        return null;
    }

    private function normalizeTripType(mixed $tripType): ?string
    {
        if (! is_string($tripType) || trim($tripType) === '') {
            return null;
        }

        $normalized = strtoupper(trim($tripType));

        return in_array($normalized, [
            TripType::MORNING_PICKUP->value,
            TripType::MORNING_RETURN->value,
            TripType::EVENING_PICKUP->value,
            TripType::EVENING_RETURN->value,
        ], true) ? $normalized : null;
    }
}
