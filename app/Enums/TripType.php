<?php

namespace App\Enums;

enum TripType: string
{
    case MORNING_PICKUP = 'MORNING_PICKUP';
    case MORNING_RETURN = 'MORNING_RETURN';
    case EVENING_PICKUP = 'EVENING_PICKUP';
    case EVENING_RETURN = 'EVENING_RETURN';

    public function pairedReturnType(): ?self
    {
        return match ($this) {
            self::MORNING_PICKUP => self::MORNING_RETURN,
            self::EVENING_PICKUP => self::EVENING_RETURN,
            default => null,
        };
    }

    public static function isPickup(string $tripType): bool
    {
        return in_array($tripType, [self::MORNING_PICKUP->value, self::EVENING_PICKUP->value], true);
    }
}
