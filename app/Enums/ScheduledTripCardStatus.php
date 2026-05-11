<?php

namespace App\Enums;

enum ScheduledTripCardStatus: string
{
    case ONGOING = 'ongoing';
    case UPCOMING = 'upcoming';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
}
