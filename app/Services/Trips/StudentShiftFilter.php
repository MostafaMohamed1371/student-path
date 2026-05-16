<?php

namespace App\Services\Trips;

use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;

final class StudentShiftFilter
{
    public function __construct(
        private readonly DriverShiftResolver $driverShiftResolver,
    ) {}

    public function shiftFromTripType(?string $tripType): ?string
    {
        if (! is_string($tripType) || trim($tripType) === '') {
            return null;
        }

        return $this->driverShiftResolver->fromTripType(trim($tripType));
    }

    /**
     * @param  Builder<Student>  $query
     */
    public function applyToStudentQuery(Builder $query, ?string $tripType): void
    {
        $shift = $this->shiftFromTripType($tripType);
        if ($shift === null) {
            return;
        }

        $query->where('shift_period', $shift);
    }

    public function studentMatchesTripType(Student $student, ?string $tripType): bool
    {
        $shift = $this->shiftFromTripType($tripType);
        if ($shift === null) {
            return true;
        }

        $studentShift = strtoupper(trim((string) ($student->shift_period ?? '')));

        return $studentShift === $shift;
    }
}
