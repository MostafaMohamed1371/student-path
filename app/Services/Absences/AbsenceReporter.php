<?php

namespace App\Services\Absences;

use App\Enums\AbsenceReason;
use App\Models\Absence;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AbsenceReporter
{
    public function __construct(
        private readonly AbsenceDriverResolver $driverResolver,
        private readonly AbsenceNotificationService $notificationService,
        private readonly AbsenceTripApplier $tripApplier,
    ) {}

    /**
     * @param  array{student_id: int, start_date: string, end_date: string, reason: string, notes?: string|null}  $payload
     *
     * @throws ValidationException
     */
    public function reportForParent(User $parentUser, array $payload): Absence
    {
        $student = Student::query()->findOrFail((int) $payload['student_id']);
        $reason = AbsenceReason::normalize((string) $payload['reason']);
        if ($reason === null) {
            throw ValidationException::withMessages([
                'reason' => [__('validation.enum', ['attribute' => 'reason'])],
            ]);
        }

        $this->driverResolver->backfillSubscriptionIfNeeded($student);

        return $this->createAbsence(
            parentUser: $parentUser,
            student: $student,
            startDate: (string) $payload['start_date'],
            endDate: (string) $payload['end_date'],
            reason: $reason,
            notes: isset($payload['notes']) ? (string) $payload['notes'] : null,
            requireSubscribedDriver: true,
        );
    }

    /**
     * @throws ValidationException
     */
    public function reportFromDashboard(User $parentUser, Student $student, array $payload): Absence
    {
        $reason = AbsenceReason::normalize((string) $payload['reason']);
        if ($reason === null) {
            throw ValidationException::withMessages([
                'reason' => [__('validation.enum', ['attribute' => 'reason'])],
            ]);
        }

        return $this->createAbsence(
            parentUser: $parentUser,
            student: $student,
            startDate: (string) $payload['start_date'],
            endDate: (string) $payload['end_date'],
            reason: $reason,
            notes: $payload['notes'] ?? null,
            requireSubscribedDriver: false,
        );
    }

    /**
     * @throws ValidationException
     */
    private function createAbsence(
        User $parentUser,
        Student $student,
        string $startDate,
        string $endDate,
        AbsenceReason $reason,
        ?string $notes,
        bool $requireSubscribedDriver,
    ): Absence {
        $student->loadMissing('transportRouteStudent.transportRoute.driver');
        ['driver' => $driver, 'transport_route' => $route] = $this->driverResolver->resolveForStudent($student);

        if ($requireSubscribedDriver && $driver === null) {
            throw ValidationException::withMessages([
                'student_id' => [__('dashboard.absence_no_subscribed_driver')],
            ]);
        }

        return DB::transaction(function () use ($parentUser, $student, $startDate, $endDate, $reason, $notes, $driver, $route): Absence {
            $absence = Absence::query()->create([
                'user_id' => $parentUser->id,
                'student_id' => $student->id,
                'driver_id' => $driver?->id,
                'transport_route_id' => $route?->id,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'reason' => $reason->value,
                'notes' => $notes !== '' ? $notes : null,
            ]);

            $this->tripApplier->apply($absence);
            $this->notificationService->notifyForAbsence($absence->fresh(['student.school', 'user.guardian', 'driver.user']));

            return $absence->fresh(['student', 'driver', 'transportRoute']);
        });
    }
}
