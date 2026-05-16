<?php

namespace Tests\Unit;

use App\Models\Student;
use App\Services\Trips\DriverShiftResolver;
use App\Services\Trips\StudentShiftFilter;
use Tests\TestCase;

class StudentShiftFilterTest extends TestCase
{
    public function test_student_matches_morning_trip_type(): void
    {
        $filter = new StudentShiftFilter(new DriverShiftResolver);
        $student = new Student(['shift_period' => 'MORNING']);

        $this->assertTrue($filter->studentMatchesTripType($student, 'MORNING_PICKUP'));
        $this->assertFalse($filter->studentMatchesTripType($student, 'EVENING_PICKUP'));
    }

    public function test_unset_student_shift_does_not_match_typed_trip(): void
    {
        $filter = new StudentShiftFilter(new DriverShiftResolver);
        $student = new Student(['shift_period' => null]);

        $this->assertFalse($filter->studentMatchesTripType($student, 'MORNING_PICKUP'));
    }
}
