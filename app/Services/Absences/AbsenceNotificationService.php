<?php

namespace App\Services\Absences;

use App\Enums\AbsenceReason;
use App\Models\Absence;
use App\Models\InAppNotification;
use App\Models\User;
use Illuminate\Support\Collection;

final class AbsenceNotificationService
{
    public function notifyForAbsence(Absence $absence): void
    {
        if (! config('trips.notifications_enabled', true)) {
            return;
        }

        $absence->loadMissing(['student.school', 'user.guardian', 'driver.user']);

        $studentName = trim((string) ($absence->student?->full_name ?? ''));
        if ($studentName === '') {
            $studentName = 'الطالب';
        }

        $parentName = $this->parentName($absence);
        $reasonLabel = $this->reasonLabel($absence);
        $dateRange = $this->dateRangeLabel($absence);

        if ($absence->driver?->user_id) {
            InAppNotification::query()->create([
                'user_id' => (int) $absence->driver->user_id,
                'title' => 'بلاغ غياب جديد',
                'body' => "بلاغ غياب من {$parentName} للطالب {$studentName} ({$reasonLabel}) {$dateRange}.",
                'data' => [
                    'type' => 'ABSENCE',
                    'absence_id' => $absence->id,
                    'student_id' => $absence->student_id,
                    'driver_id' => $absence->driver_id,
                    'start_date' => $absence->start_date?->toDateString(),
                    'end_date' => $absence->end_date?->toDateString(),
                    'reason' => $absence->reason,
                ],
            ]);

            $absence->forceFill(['driver_notified_at' => now()])->save();
        }

        $schoolUsers = $this->schoolRecipients($absence);
        foreach ($schoolUsers as $user) {
            InAppNotification::query()->create([
                'user_id' => (int) $user->id,
                'title' => 'بلاغ غياب ولي أمر',
                'body' => "بلاغ غياب للطالب {$studentName} ({$reasonLabel}) {$dateRange}.",
                'data' => [
                    'type' => 'ABSENCE',
                    'absence_id' => $absence->id,
                    'student_id' => $absence->student_id,
                    'school_id' => $absence->student?->school_id,
                    'start_date' => $absence->start_date?->toDateString(),
                    'end_date' => $absence->end_date?->toDateString(),
                    'reason' => $absence->reason,
                ],
            ]);
        }

        if ($schoolUsers->isNotEmpty()) {
            $absence->forceFill(['school_notified_at' => now()])->save();
        }
    }

    private function parentName(Absence $absence): string
    {
        $candidates = [
            $absence->user?->guardian?->full_name,
            $absence->user?->name,
            $absence->student?->guardian_name,
        ];

        foreach ($candidates as $name) {
            if (is_string($name) && trim($name) !== '') {
                return trim($name);
            }
        }

        return 'ولي الأمر';
    }

    private function reasonLabel(Absence $absence): string
    {
        $reason = AbsenceReason::normalize((string) $absence->reason);

        return $reason?->labelAr() ?? (string) $absence->reason;
    }

    private function dateRangeLabel(Absence $absence): string
    {
        $start = $absence->start_date?->toDateString() ?? '';
        $end = $absence->end_date?->toDateString() ?? '';

        if ($start !== '' && $end !== '' && $start !== $end) {
            return "من {$start} إلى {$end}";
        }

        return $start !== '' ? "بتاريخ {$start}" : '';
    }

    /**
     * @return Collection<int, User>
     */
    private function schoolRecipients(Absence $absence): Collection
    {
        $schoolId = (int) ($absence->student?->school_id ?? 0);
        if ($schoolId <= 0) {
            return collect();
        }

        $parentUserId = (int) ($absence->user_id ?? 0);

        return User::query()
            ->where(function ($query) use ($schoolId): void {
                $query->where('school_id', $schoolId)
                    ->orWhere('is_admin', true);
            })
            ->whereDoesntHave('driver')
            ->whereNull('guardian_id')
            ->when($parentUserId > 0, fn ($q) => $q->where('id', '!=', $parentUserId))
            ->get();
    }
}
