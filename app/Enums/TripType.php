<?php

namespace App\Enums;

enum TripType: string
{
    case MORNING_PICKUP = 'MORNING_PICKUP';
    case MORNING_RETURN = 'MORNING_RETURN';
    case EVENING_PICKUP = 'EVENING_PICKUP';
    case EVENING_RETURN = 'EVENING_RETURN';
}
