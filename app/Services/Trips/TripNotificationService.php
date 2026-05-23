<?php

namespace App\Services\Trips;

use App\Enums\TripType;
use App\Models\InAppNotification;
use App\Models\Student;
use App\Models\TripHistory;
use App\Models\User;
use Illuminate\Support\Collection;

final class TripNotificationService
{
    public function notifyTripStarted(TripHistory $trip): void
    {
        if (! config('trips.notifications_enabled', true)) {
            return;
        }

        $trip->loadMissing('tripHistoryStudents.student');
        $isReturn = $this->isReturnTrip($trip);

        $this->notifyGuardiansOnTrip(
            $trip,
            $isReturn ? 'RETURN_TRIP_STARTED' : 'TRIP_STARTED',
            $isReturn ? 'بدء رحلة العودة' : 'بدء تحرك الحافلة',
            $isReturn
                ? 'بدأ السائق رحلة العودة'
                : 'بدأ السائق الرحلة ويتم تتبع الحافلة',
        );
    }

    public function notifyTripCompleted(TripHistory $trip): void
    {
        if (! config('trips.notifications_enabled', true)) {
            return;
        }

        $isReturn = $this->isReturnTrip($trip);

        $this->notifyGuardiansOnTrip(
            $trip,
            $isReturn ? 'RETURN_TRIP_COMPLETED' : 'TRIP_COMPLETED',
            $isReturn ? 'انتهاء رحلة العودة' : 'انتهاء الرحلة',
            $isReturn
                ? 'تم إنهاء رحلة العودة بنجاح'
                : 'تم إنهاء الرحلة بنجاح',
        );
    }

    public function notifyStudentArrived(TripHistory $trip, Student $student): void
    {
        if (! config('trips.notifications_enabled', true)) {
            return;
        }

        $user = $this->userForStudent($student);
        if (! $user) {
            return;
        }

        $studentName = trim((string) ($student->full_name ?? 'الطالب'));

        $this->create(
            $user,
            'وصول الحافلة',
            "وصلت الحافلة إلى موقع {$studentName}",
            [
                'type' => 'TRIP_STUDENT_ARRIVED',
                'trip_id' => $this->externalTripId($trip),
                'student_id' => $student->id,
                'student_name' => $studentName,
            ],
        );
    }

    private function notifyGuardiansOnTrip(
        TripHistory $trip,
        string $type,
        string $title,
        string $body,
    ): void {
        foreach ($this->guardianUsersForTrip($trip) as $user) {
            $this->create($user, $title, $body, [
                'type' => $type,
                'trip_id' => $this->externalTripId($trip),
                'trip_type' => $trip->trip_type,
            ]);
        }
    }

    private function externalTripId(TripHistory $trip): string
    {
        return 'TRP-'.$trip->id;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function create(User $user, string $title, string $body, array $data): void
    {
        InAppNotification::query()->create([
            'user_id' => $user->id,
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ]);
    }

    /**
     * @return Collection<int, User>
     */
    private function guardianUsersForTrip(TripHistory $trip): Collection
    {
        $trip->loadMissing('tripHistoryStudents.student');

        $guardianIds = $trip->tripHistoryStudents
            ->map(fn ($ths) => $ths->student?->guardian_id)
            ->filter(fn ($id) => is_int($id) || ctype_digit((string) $id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($guardianIds === []) {
            return collect();
        }

        return User::query()->whereIn('guardian_id', $guardianIds)->get()->unique('id')->values();
    }

    private function userForStudent(Student $student): ?User
    {
        if (! $student->guardian_id) {
            return null;
        }

        return User::query()->where('guardian_id', $student->guardian_id)->first();
    }

    private function isReturnTrip(TripHistory $trip): bool
    {
        $type = strtoupper((string) ($trip->trip_type ?? ''));

        return in_array($type, [
            TripType::MORNING_RETURN->value,
            TripType::EVENING_RETURN->value,
        ], true);
    }
}
