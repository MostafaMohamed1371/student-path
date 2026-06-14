<?php

namespace App\Support;

use Illuminate\Support\Carbon;

final class SupportComplaintReference
{
    public static function format(int $complaintId, ?Carbon $submittedAt = null): string
    {
        $year = ($submittedAt ?? now())->format('Y');

        return '#CMP-'.$year.'-'.str_pad((string) $complaintId, 6, '0', STR_PAD_LEFT);
    }
}
