<?php

namespace App\Services\Attendance;

use App\Enums\AbsenceReason;
use App\Enums\DelayReasonType;
use App\Enums\StudentAttendanceDayStatus;
use App\Enums\StudentTripStopStatus;
use App\Models\Absence;
use App\Models\DelayAlert;
use App\Models\Student;
use App\Models\TripHistoryStudent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class StudentAttendanceScheduleService
{
    /**
     * @return array{
     *   student_id: int,
     *   year: int,
     *   month: int,
     *   month_label_en: string,
     *   month_label_ar: string,
     *   summary: array{present_days: int, absence_days: int, late_count: int, present_color: string, absence_color: string, late_color: string},
     *   status_legend: list<array{status: string, label_en: string, label_ar: string, color: string, icon: string}>,
     *   calendar: list<array{date: string, day_of_month: int, weekday: int, status: string|null, status_label_en: string|null, status_label_ar: string|null, status_color: string|null, status_icon: string|null}>,
     *   recent_events: list<array<string, mixed>>
     * }
     */
    public function scheduleForStudent(Student $student, int $year, int $month, int $recentLimit = 10): array
    {
        $monthStart = Carbon::create($year, $month, 1)->startOfDay();
        $monthEnd = $monthStart->copy()->endOfMonth()->endOfDay();

        $absences = $this->absencesInRange((int) $student->id, $monthStart, $monthEnd);
        $tripRows = $this->tripRowsInRange((int) $student->id, $monthStart, $monthEnd);
        $delayAlerts = $this->delayAlertsForStudentTrips($tripRows);
        $tripDates = $this->tripDatesById($tripRows);

        $dayStatuses = [];
        $cursor = $monthStart->copy();
        while ($cursor->lte($monthEnd)) {
            $date = $cursor->toDateString();
            $dayStatuses[$date] = $this->resolveDayStatus($date, $absences, $tripRows, $delayAlerts);
            $cursor->addDay();
        }

        $summary = [
            'present_days' => collect($dayStatuses)->filter(fn (?StudentAttendanceDayStatus $s) => $s === StudentAttendanceDayStatus::Present)->count(),
            'absence_days' => collect($dayStatuses)->filter(fn (?StudentAttendanceDayStatus $s) => $s === StudentAttendanceDayStatus::Absent)->count(),
            'late_count' => $delayAlerts->count(),
            'present_color' => StudentAttendanceDayStatus::Present->colorHex(),
            'absence_color' => StudentAttendanceDayStatus::Absent->colorHex(),
            'late_color' => StudentAttendanceDayStatus::Late->colorHex(),
        ];

        $calendar = [];
        foreach ($dayStatuses as $date => $status) {
            $day = Carbon::parse($date);
            $calendar[] = [
                'date' => $date,
                'day_of_month' => (int) $day->day,
                'weekday' => (int) $day->dayOfWeek,
                'status' => $status?->value,
                'status_label_en' => $status?->labelEn(),
                'status_label_ar' => $status?->labelAr(),
                'status_color' => $status?->colorHex(),
                'status_icon' => $status?->icon(),
            ];
        }

        return [
            'student_id' => (int) $student->id,
            'year' => $year,
            'month' => $month,
            'month_label_en' => $monthStart->copy()->locale('en')->translatedFormat('F Y'),
            'month_label_ar' => $monthStart->copy()->locale('ar')->translatedFormat('F Y'),
            'summary' => $summary,
            'status_legend' => StudentAttendanceDayStatus::legend(),
            'calendar' => $calendar,
            'recent_events' => $this->recentEvents(
                $absences,
                $delayAlerts,
                $tripDates,
                $recentLimit,
            ),
        ];
    }

    /**
     * @return Collection<int, Absence>
     */
    private function absencesInRange(int $studentId, Carbon $from, Carbon $to): Collection
    {
        return Absence::query()
            ->where('student_id', $studentId)
            ->whereDate('start_date', '<=', $to->toDateString())
            ->whereDate('end_date', '>=', $from->toDateString())
            ->orderByDesc('start_date')
            ->get();
    }

    /**
     * @return Collection<int, TripHistoryStudent>
     */
    private function tripRowsInRange(int $studentId, Carbon $from, Carbon $to): Collection
    {
        return TripHistoryStudent::query()
            ->where('student_id', $studentId)
            ->whereHas('tripHistory', fn ($q) => $q->whereBetween('start_time', [$from, $to]))
            ->with(['tripHistory.delayAlerts'])
            ->get();
    }

    /**
     * @param  Collection<int, TripHistoryStudent>  $tripRows
     * @return Collection<int, DelayAlert>
     */
    private function delayAlertsForStudentTrips(Collection $tripRows): Collection
    {
        return $tripRows
            ->flatMap(fn (TripHistoryStudent $row) => $row->tripHistory?->delayAlerts ?? collect())
            ->unique('id')
            ->sortByDesc('created_at')
            ->values();
    }

    /**
     * @param  Collection<int, Absence>  $absences
     * @param  Collection<int, TripHistoryStudent>  $tripRows
     * @param  Collection<int, DelayAlert>  $delayAlerts
     */
    private function resolveDayStatus(
        string $date,
        Collection $absences,
        Collection $tripRows,
        Collection $delayAlerts,
    ): ?StudentAttendanceDayStatus {
        foreach ($absences as $absence) {
            if ($this->dateInAbsenceRange($date, $absence)) {
                return StudentAttendanceDayStatus::Absent;
            }
        }

        $tripsOnDay = $tripRows->filter(function (TripHistoryStudent $row) use ($date): bool {
            $tripDate = $row->tripHistory?->start_time?->toDateString();

            return $tripDate === $date;
        });

        foreach ($tripsOnDay as $row) {
            $stop = StudentTripStopStatus::tryFrom((string) $row->status) ?? StudentTripStopStatus::IDLE;
            if ($stop === StudentTripStopStatus::ABSENT) {
                return StudentAttendanceDayStatus::Absent;
            }
        }

        $tripIdsOnDay = $tripsOnDay
            ->map(fn (TripHistoryStudent $row): int => (int) ($row->trip_history_id ?? 0))
            ->filter(fn (int $id): bool => $id > 0);

        if ($delayAlerts->contains(fn (DelayAlert $alert): bool => $tripIdsOnDay->contains((int) $alert->trip_history_id))) {
            return StudentAttendanceDayStatus::Late;
        }

        foreach ($tripsOnDay as $row) {
            $stop = StudentTripStopStatus::tryFrom((string) $row->status) ?? StudentTripStopStatus::IDLE;
            if ($stop === StudentTripStopStatus::BOARDED) {
                return StudentAttendanceDayStatus::Present;
            }
        }

        return null;
    }

    private function dateInAbsenceRange(string $date, Absence $absence): bool
    {
        $start = $absence->start_date?->toDateString() ?? '';
        $end = $absence->end_date?->toDateString() ?? '';

        return $start !== '' && $end !== '' && $date >= $start && $date <= $end;
    }

    /**
     * @param  Collection<int, TripHistoryStudent>  $tripRows
     * @return array<int, string>
     */
    private function tripDatesById(Collection $tripRows): array
    {
        $map = [];
        foreach ($tripRows as $row) {
            $tripId = (int) ($row->trip_history_id ?? 0);
            $date = $row->tripHistory?->start_time?->toDateString();
            if ($tripId > 0 && is_string($date) && $date !== '') {
                $map[$tripId] = $date;
            }
        }

        return $map;
    }

    /**
     * @param  Collection<int, Absence>  $absences
     * @param  Collection<int, DelayAlert>  $delayAlerts
     * @param  array<int, string>  $tripDates
     * @return list<array<string, mixed>>
     */
    private function recentEvents(
        Collection $absences,
        Collection $delayAlerts,
        array $tripDates,
        int $limit,
    ): array {
        $events = [];

        foreach ($absences as $absence) {
            $eventDate = $absence->start_date?->toDateString();
            if ($eventDate === null) {
                continue;
            }

            $reason = AbsenceReason::tryFrom((string) $absence->reason);
            $reasonText = trim((string) ($absence->notes ?? ''));
            if ($reasonText === '') {
                $reasonText = $reason?->labelAr() ?? (string) $absence->reason;
            }

            $day = Carbon::parse($eventDate);
            $events[] = [
                'type' => 'absence',
                'date' => $eventDate,
                'day_name_en' => $day->copy()->locale('en')->translatedFormat('l'),
                'day_name_ar' => $day->copy()->locale('ar')->translatedFormat('l'),
                'absence_id' => $absence->id,
                'reason_code' => $reason?->value,
                'reason_label_en' => $reason?->labelEn(),
                'reason_label_ar' => $reason?->labelAr(),
                'reason_text' => $reasonText,
                'color' => StudentAttendanceDayStatus::Absent->colorHex(),
                'icon' => StudentAttendanceDayStatus::Absent->icon(),
            ];
        }

        foreach ($delayAlerts as $alert) {
            $tripDate = $tripDates[(int) $alert->trip_history_id] ?? null;
            if ($tripDate === null) {
                continue;
            }

            $reasonType = DelayReasonType::tryFrom((string) $alert->reason_type);
            $day = Carbon::parse($tripDate);
            $events[] = [
                'type' => 'late',
                'date' => $tripDate,
                'day_name_en' => $day->copy()->locale('en')->translatedFormat('l'),
                'day_name_ar' => $day->copy()->locale('ar')->translatedFormat('l'),
                'trip_history_id' => $alert->trip_history_id,
                'delay_minutes' => (int) $alert->delay_duration_minutes,
                'reason_type' => $alert->reason_type,
                'reason_label_en' => $this->delayReasonLabelEn($reasonType),
                'reason_label_ar' => $this->delayReasonLabelAr($reasonType),
                'note' => $alert->note,
                'color' => StudentAttendanceDayStatus::Late->colorHex(),
                'icon' => StudentAttendanceDayStatus::Late->icon(),
            ];
        }

        usort($events, fn (array $a, array $b): int => strcmp((string) $b['date'], (string) $a['date']));

        return array_slice($events, 0, max(1, $limit));
    }

    private function delayReasonLabelEn(?DelayReasonType $type): string
    {
        return match ($type) {
            DelayReasonType::TRAFFIC => 'Traffic',
            DelayReasonType::MECHANICAL_ISSUES => 'Mechanical issues',
            DelayReasonType::STUDENT_DELAY => 'Student delay',
            DelayReasonType::OTHER => 'Other',
            default => 'Delay',
        };
    }

    private function delayReasonLabelAr(?DelayReasonType $type): string
    {
        return match ($type) {
            DelayReasonType::TRAFFIC => 'ازدحام مروري',
            DelayReasonType::MECHANICAL_ISSUES => 'عطل ميكانيكي',
            DelayReasonType::STUDENT_DELAY => 'تأخر الطالب',
            DelayReasonType::OTHER => 'أخرى',
            default => 'تأخير',
        };
    }
}
