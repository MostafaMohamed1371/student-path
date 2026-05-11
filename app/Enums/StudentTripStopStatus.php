<?php

namespace App\Enums;

enum StudentTripStopStatus: string
{
    case IDLE = 'IDLE';
    case ON_WAY = 'ON_WAY';
    case ARRIVED = 'ARRIVED';
    case BOARDED = 'BOARDED';
    case ABSENT = 'ABSENT';
}
