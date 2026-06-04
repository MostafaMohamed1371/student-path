<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;

final class AgeFromDateOfBirth
{
    /**
     * Whole years between the given date (Y-m-d) and today.
     */
    public static function fromDateString(?string $dateOfBirth): ?int
    {
        if ($dateOfBirth === null || trim($dateOfBirth) === '') {
            return null;
        }

        try {
            return Carbon::parse($dateOfBirth)->age;
        } catch (InvalidFormatException) {
            return null;
        }
    }
}
