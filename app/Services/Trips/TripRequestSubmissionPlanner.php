<?php

namespace App\Services\Trips;

use App\Models\Driver;
use App\Models\Student;
use App\Models\TripHistory;
use App\Models\User;
use App\Support\ParentContext;
use Illuminate\Validation\ValidationException;

/**
 * Shared rules for POST /api/trip-requests and dashboard trip-request create.
 */
final class TripRequestSubmissionPlanner
{
    public function __construct(
        private readonly DriverShiftResolver $shiftResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $snapshotOverrides  present_type, moving_point, stop_point, subscribe_price
     *
     * @throws ValidationException
     */
    public function plan(
        User $parentUser,
        Student $student,
        ?TripHistory $trip = null,
        ?int $explicitDriverId = null,
        ?string $presentType = null,
        array $snapshotOverrides = [],
    ): TripRequestSubmissionPlan {
        $student->loadMissing(['school', 'guardian']);
        ParentContext::ensureUserLinkedToStudent($parentUser, $student);

        if ($trip !== null) {
            $this->assertTripMatchesStudentSchools($parentUser, $student, $trip);
            $this->assertStudentShiftMatchesTrip($student, $trip);
        }

        $targetShift = $this->shiftResolver->fromPresentType($presentType);
        if ($targetShift === null && $trip !== null) {
            $targetShift = $this->shiftResolver->fromTripType($trip->trip_type);
        }

        $driverId = $this->resolveDriverId($student, $trip, $explicitDriverId, $targetShift);
        $assignedDriver = $driverId !== null ? Driver::query()->find($driverId) : null;

        $snapshot = TripRequestOrderSnapshot::build($student, $assignedDriver, $snapshotOverrides);

        return new TripRequestSubmissionPlan(
            driverId: $driverId,
            tripHistoryId: $trip?->id,
            targetShift: $targetShift,
            snapshot: $snapshot,
        );
    }

    /**
     * @throws ValidationException
     */
    public function assertTripMatchesStudentSchools(User $parentUser, Student $student, TripHistory $trip): void
    {
        if ((int) $trip->school_id !== (int) $student->school_id) {
            throw ValidationException::withMessages([
                'trip_history_id' => [__('dashboard.trip_request_trip_school_mismatch')],
            ]);
        }

        $allowedSchools = ParentContext::studentsFor($parentUser)->pluck('school_id')->unique()->filter();
        if ($allowedSchools->isNotEmpty() && ! $allowedSchools->contains($trip->school_id)) {
            throw ValidationException::withMessages([
                'trip_history_id' => [__('dashboard.trip_request_trip_out_of_scope')],
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    public function assertStudentShiftMatchesTrip(Student $student, TripHistory $trip): void
    {
        $tripShift = $this->shiftResolver->fromTripType($trip->trip_type);
        $studentShift = is_string($student->shift_period) ? trim($student->shift_period) : '';

        if ($tripShift === null || $studentShift === '' || $studentShift === 'BOTH') {
            return;
        }

        if ($studentShift !== $tripShift) {
            throw ValidationException::withMessages([
                'trip_history_id' => [__('dashboard.trip_request_student_shift_mismatch')],
            ]);
        }
    }

    private function resolveDriverId(
        Student $student,
        ?TripHistory $trip,
        ?int $explicitDriverId,
        ?string $targetShift,
    ): ?int {
        if ($explicitDriverId !== null && $explicitDriverId > 0) {
            return $this->validateExplicitDriver($student, $explicitDriverId, $targetShift);
        }

        if ($trip !== null && $trip->driver_id) {
            $tripDriverId = $this->driverIdFromTrip($student, $trip, $targetShift);
            if ($tripDriverId !== null) {
                return $tripDriverId;
            }
        }

        return $this->autoAssignDriverId((int) $student->school_id, $targetShift);
    }

    /**
     * @throws ValidationException
     */
    private function validateExplicitDriver(Student $student, int $driverId, ?string $targetShift): int
    {
        $chosen = Driver::query()->findOrFail($driverId);

        if ($chosen->status !== 'active') {
            throw ValidationException::withMessages([
                'driver_id' => [__('dashboard.trip_request_driver_inactive')],
            ]);
        }

        if ((int) $chosen->school_id !== (int) $student->school_id) {
            throw ValidationException::withMessages([
                'driver_id' => [__('dashboard.trip_request_driver_school_mismatch')],
            ]);
        }

        if ($targetShift !== null
            && $chosen->shift_period !== null
            && $chosen->shift_period !== 'BOTH'
            && $chosen->shift_period !== $targetShift) {
            throw ValidationException::withMessages([
                'driver_id' => [__('dashboard.trip_request_driver_shift_mismatch')],
            ]);
        }

        return (int) $chosen->id;
    }

    private function driverIdFromTrip(Student $student, TripHistory $trip, ?string $targetShift): ?int
    {
        $driver = Driver::query()->find((int) $trip->driver_id);
        if ($driver === null || $driver->status !== 'active') {
            return null;
        }

        if ((int) $driver->school_id !== (int) $student->school_id) {
            return null;
        }

        if ($targetShift !== null
            && $driver->shift_period !== null
            && $driver->shift_period !== 'BOTH'
            && $driver->shift_period !== $targetShift) {
            return null;
        }

        return (int) $driver->id;
    }

    private function autoAssignDriverId(int $schoolId, ?string $targetShift): ?int
    {
        $query = Driver::query()
            ->where('school_id', $schoolId)
            ->where('status', 'active')
            ->orderBy('id');

        if ($targetShift !== null) {
            $matching = (clone $query)
                ->where(function ($q) use ($targetShift): void {
                    $q->where('shift_period', $targetShift)->orWhere('shift_period', 'BOTH');
                })
                ->value('id');
            if ($matching !== null) {
                return (int) $matching;
            }
        }

        $fallback = $query->value('id');

        return $fallback !== null ? (int) $fallback : null;
    }
}
