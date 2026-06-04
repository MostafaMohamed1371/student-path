<?php

namespace App\Http\Requests\Concerns;

use App\Support\AgeFromDateOfBirth;

trait SyncsAgeFromDateOfBirth
{
    protected function syncAgeFromDateOfBirth(): void
    {
        $dateOfBirth = $this->input('date_of_birth');
        if ($dateOfBirth === null || $dateOfBirth === '') {
            return;
        }

        $age = AgeFromDateOfBirth::fromDateString((string) $dateOfBirth);
        if ($age !== null) {
            $this->merge(['age' => $age]);
        }
    }
}
