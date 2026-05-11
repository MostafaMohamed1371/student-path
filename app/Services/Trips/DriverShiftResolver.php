<?php

namespace App\Services\Trips;

use App\Enums\TripType;

final class DriverShiftResolver
{
    public const MORNING = 'MORNING';
    public const EVENING = 'EVENING';

    public function fromPresentType(?string $presentType): ?string
    {
        if (! is_string($presentType) || trim($presentType) === '') {
            return null;
        }
        $t = mb_strtolower(trim($presentType));
        if (str_contains($t, 'صباح')) {
            return self::MORNING;
        }
        if (str_contains($t, 'مساء') || str_contains($t, 'مسائي')) {
            return self::EVENING;
        }

        return null;
    }

    public function fromTripType(?string $tripType): ?string
    {
        return match ($tripType) {
            TripType::MORNING_PICKUP->value, TripType::MORNING_RETURN->value => self::MORNING,
            TripType::EVENING_PICKUP->value, TripType::EVENING_RETURN->value => self::EVENING,
            default => null,
        };
    }
}
