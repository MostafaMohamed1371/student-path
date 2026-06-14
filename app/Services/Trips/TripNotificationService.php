<?php

namespace App\Services\Trips;

use App\Enums\TripType;
use App\Models\InAppNotification;
use App\Models\SosAlert;
use App\Models\Student;
use App\Models\TripHistory;
use App\Models\User;
use Illuminate\Support\Collection;

final class TripNotificationService
{
    public function notifyTripStarted(TripHistory $trip): void
    {
        if (! $this->enabled()) {
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
        if (! $this->enabled()) {
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

    public function notifyStudentOnWay(TripHistory $trip, Student $student): void
    {
        if (! $this->enabled()) {
            return;
        }

        $user = $this->userForStudent($student);
        if (! $user) {
            return;
        }

        $studentName = trim((string) ($student->full_name ?? 'الطالب'));

        $this->create(
            $user,
            'الحافلة في الطريق',
            "الحافلة في الطريق إلى موقع {$studentName}",
            [
                'type' => 'TRIP_STUDENT_ON_WAY',
                'trip_id' => $this->externalTripId($trip),
                'student_id' => $student->id,
                'student_name' => $studentName,
            ],
        );
    }

    public function notifyStudentArrived(TripHistory $trip, Student $student): void
    {
        if (! $this->enabled()) {
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

    public function notifyStudentBoarded(TripHistory $trip, Student $student): void
    {
        if (! $this->enabled()) {
            return;
        }

        $user = $this->userForStudent($student);
        if (! $user) {
            return;
        }

        $studentName = trim((string) ($student->full_name ?? 'الطالب'));

        $this->create(
            $user,
            'صعود الطالب',
            "صعد {$studentName} إلى الحافلة",
            [
                'type' => 'TRIP_STUDENT_BOARDED',
                'trip_id' => $this->externalTripId($trip),
                'student_id' => $student->id,
                'student_name' => $studentName,
            ],
        );
    }

    public function notifyDelayAlert(TripHistory $trip, int $delayMinutes, string $reasonType): void
    {
        if (! $this->enabled()) {
            return;
        }

        $this->notifyGuardiansOnTrip(
            $trip,
            'DELAY_ALERT',
            'تنبيه تأخير الرحلة',
            'تم إرسال بلاغ تأخير لمدة '.$delayMinutes.' دقيقة',
            [
                'reason_type' => $reasonType,
                'delay_duration_minutes' => $delayMinutes,
            ],
        );
    }

    public function notifySosTriggered(TripHistory $trip, SosAlert $sos): void
    {
        if (! $this->enabled()) {
            return;
        }

        foreach ($this->sosRecipientsForTrip($trip) as $user) {
            $this->create(
                $user,
                'نداء استغاثة طارئ',
                'تم إرسال نداء استغاثة من السائق ويجري تتبع الموقع',
                [
                    'type' => 'SOS_TRIGGERED',
                    'sos_id' => 'SOS-'.$sos->id,
                    'trip_id' => $this->externalTripId($trip),
                ],
            );
        }
    }

    public function notifySosStopped(TripHistory $trip, SosAlert $sos, ?string $reason = null): void
    {
        if (! $this->enabled()) {
            return;
        }

        $body = $reason !== null && trim($reason) !== ''
            ? 'تم إنهاء حالة الطوارئ: '.trim($reason)
            : 'تم إنهاء حالة الطوارئ بنجاح';

        foreach ($this->sosRecipientsForTrip($trip) as $user) {
            $this->create(
                $user,
                'انتهاء نداء الاستغاثة',
                $body,
                [
                    'type' => 'SOS_STOPPED',
                    'sos_id' => 'SOS-'.$sos->id,
                    'trip_id' => $this->externalTripId($trip),
                ],
            );
        }
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function notifyGuardiansOnTrip(
        TripHistory $trip,
        string $type,
        string $title,
        string $body,
        array $extra = [],
    ): void {
        foreach ($this->guardianUsersForTrip($trip) as $user) {
            $this->create($user, $title, $body, array_merge([
                'type' => $type,
                'trip_id' => $this->externalTripId($trip),
                'trip_type' => $trip->trip_type,
            ], $extra));
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

    /**
     * @return Collection<int, User>
     */
    private function sosRecipientsForTrip(TripHistory $trip): Collection
    {
        $guardians = $this->guardianUsersForTrip($trip);

        $admins = User::query()
            ->where('is_admin', true)
            ->where(function ($query) use ($trip): void {
                $query->whereNull('school_id')
                    ->orWhere('school_id', (int) $trip->school_id);
            })
            ->get();

        return $guardians->merge($admins)->unique('id')->values();
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

    private function enabled(): bool
    {
        return (bool) config('trips.notifications_enabled', true);
    }
}
