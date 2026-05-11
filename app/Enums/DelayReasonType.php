<?php

namespace App\Enums;

enum DelayReasonType: string
{
    case TRAFFIC = 'TRAFFIC';
    case MECHANICAL_ISSUES = 'MECHANICAL_ISSUES';
    case STUDENT_DELAY = 'STUDENT_DELAY';
    case OTHER = 'OTHER';
}
