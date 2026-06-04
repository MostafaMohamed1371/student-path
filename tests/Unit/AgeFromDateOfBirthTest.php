<?php

namespace Tests\Unit;

use App\Support\AgeFromDateOfBirth;
use Carbon\Carbon;
use Tests\TestCase;

class AgeFromDateOfBirthTest extends TestCase
{
    public function test_calculates_whole_years_from_date_of_birth(): void
    {
        Carbon::setTestNow('2026-06-04');

        $this->assertSame(10, AgeFromDateOfBirth::fromDateString('2016-06-04'));
        $this->assertSame(9, AgeFromDateOfBirth::fromDateString('2016-12-31'));

        Carbon::setTestNow();
    }

    public function test_returns_null_for_empty_or_invalid_date(): void
    {
        $this->assertNull(AgeFromDateOfBirth::fromDateString(null));
        $this->assertNull(AgeFromDateOfBirth::fromDateString(''));
        $this->assertNull(AgeFromDateOfBirth::fromDateString('not-a-date'));
    }
}
