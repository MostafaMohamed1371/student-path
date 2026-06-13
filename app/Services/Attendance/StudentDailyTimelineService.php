<?php

namespace App\Services\Attendance;

use App\Enums\StudentTimelineMilestoneCode;
use App\Enums\StudentTimelineMilestoneStatus;
use App\Enums\StudentTripStopStatus;
use App\Enums\TripType;
use App\Models\Absence;
use App\Models\Student;
use App\Models\TripHistory;
use App\Models\TripHistoryStudent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class StudentDailyTimelineService
{
    /**
     * @return array{
     *   student_id: int,
     *   date: string,
     *   date_label_en: string,
     *   date_label_ar: string,
     *   student: array{id: int, full_name: string, grade: string|null, school_name_en: string|null, school_name_ar: string|null, profile_photo: string|null},
     *   is_absent_today: bool,
     *   milestones: list<array<string, mixed>>
     * }
     */
    public function timelineForStudent(Student $student, Carbon $date): array
    {
        $student->loadMissing(['school', 'guardian']);
        $day = $date->copy()->startOfDay();
        $dayString = $day->toDateString();

        $isAbsent = $this->isAbsentOnDate((int) $student->id, $day);
        $tripRows = $this->tripRowsForDate((int) $student->id, $day);
        $tripsByType = $this->tripsByType($tripRows);

        $morningPickup = $tripsByType[TripType::MORNING_PICKUP->value] ?? null;
        $morningReturn = $tripsByType[TripType::MORNING_RETURN->value] ?? null;
        $eveningReturn = $tripsByType[TripType::EVENING_RETURN->value] ?? null;
        $returnTrip = $this->resolveReturnTripContext($morningReturn, $eveningReturn);

        $defaults = config('trips.default_daily_timeline', []);

        $milestones = [
            $this->buildMorningPickupHome($student, $day, $morningPickup, $isAbsent, $defaults),
            $this->buildMorningArriveSchool($day, $morningPickup, $isAbsent, $defaults),
            $this->buildEveningPickupSchool($day, $returnTrip, $isAbsent, $defaults),
            $this->buildEveningArriveHome($day, $returnTrip, $isAbsent, $defaults),
        ];

        return [
            'student_id' => (int) $student->id,
            'date' => $dayString,
            'date_label_en' => $day->copy()->locale('en')->translatedFormat('l, F j'),
            'date_label_ar' => 'اليوم ، '.$day->copy()->locale('ar')->translatedFormat('j F'),
            'student' => $this->studentHeader($student),
            'is_absent_today' => $isAbsent,
            'milestones' => $milestones,
        ];
    }

    /**
     * @param  array<string, string>  $defaults
     * @return array<string, mixed>
     */
    private function buildMorningPickupHome(
        Student $student,
        Carbon $day,
        ?array $context,
        bool $isAbsent,
        array $defaults,
    ): array {
        $code = StudentTimelineMilestoneCode::MorningPickupHome;
        $scheduled = $this->scheduledAt($day, $context['trip'] ?? null, (string) ($defaults['morning_pickup'] ?? '07:15'));

        if ($isAbsent) {
            return $this->milestonePayload($code, StudentTimelineMilestoneStatus::Absent, $scheduled, null, $this->homeDescription($student));
        }

        $stop = $context['stop'] ?? null;
        $status = $this->pickupStatus($stop);
        $actual = $stop?->boarding_time ?? ($status === StudentTimelineMilestoneStatus::Arrived ? $stop?->arrived_at : null);

        return $this->milestonePayload($code, $status, $scheduled, $actual, $this->homeDescription($student));
    }

    /**
     * @param  array<string, string>  $defaults
     * @return array<string, mixed>
     */
    private function buildMorningArriveSchool(
        Carbon $day,
        ?array $context,
        bool $isAbsent,
        array $defaults,
    ): array {
        $code = StudentTimelineMilestoneCode::MorningArriveSchool;
        $trip = $context['trip'] ?? null;
        $scheduled = $this->scheduledAt($day, $trip, (string) ($defaults['morning_school_arrival'] ?? '07:45'), useEndTime: true);

        if ($isAbsent) {
            return $this->milestonePayload($code, StudentTimelineMilestoneStatus::Absent, $scheduled, null);
        }

        $stop = $context['stop'] ?? null;
        $status = StudentTimelineMilestoneStatus::Scheduled;
        $actual = null;

        if ($trip instanceof TripHistory && $this->tripCompleted($trip) && $stop && StudentTripStopStatus::tryFrom((string) $stop->status) === StudentTripStopStatus::BOARDED) {
            $status = StudentTimelineMilestoneStatus::Completed;
            $actual = $trip->end_time;
        } elseif ($stop && StudentTripStopStatus::tryFrom((string) $stop->status) === StudentTripStopStatus::BOARDED) {
            $status = StudentTimelineMilestoneStatus::OnWay;
        }

        return $this->milestonePayload($code, $status, $scheduled, $actual);
    }

    /**
     * @param  array<string, string>  $defaults
     * @return array<string, mixed>
     */
    private function buildEveningPickupSchool(
        Carbon $day,
        ?array $context,
        bool $isAbsent,
        array $defaults,
    ): array {
        $code = StudentTimelineMilestoneCode::EveningPickupSchool;
        $scheduled = $this->scheduledAt($day, $context['trip'] ?? null, (string) ($defaults['evening_pickup'] ?? '15:30'));

        if ($isAbsent) {
            return $this->milestonePayload($code, StudentTimelineMilestoneStatus::Absent, $scheduled, null);
        }

        $stop = $context['stop'] ?? null;
        $status = $this->pickupStatus($stop, defaultScheduled: true);
        $actual = $stop?->boarding_time;

        return $this->milestonePayload($code, $status, $scheduled, $actual);
    }

    /**
     * @param  array<string, string>  $defaults
     * @return array<string, mixed>
     */
    private function buildEveningArriveHome(
        Carbon $day,
        ?array $returnTrip,
        bool $isAbsent,
        array $defaults,
    ): array {
        $code = StudentTimelineMilestoneCode::EveningArriveHome;
        $trip = $returnTrip['trip'] ?? null;
        $stop = $returnTrip['stop'] ?? null;
        $scheduled = $this->scheduledAt($day, $trip, (string) ($defaults['evening_home_arrival'] ?? '16:15'), useEndTime: true);

        if ($isAbsent) {
            return $this->milestonePayload($code, StudentTimelineMilestoneStatus::Absent, $scheduled, null);
        }

        $status = StudentTimelineMilestoneStatus::Scheduled;
        $actual = null;

        if ($trip instanceof TripHistory && $this->tripCompleted($trip) && $stop && StudentTripStopStatus::tryFrom((string) $stop->status) === StudentTripStopStatus::BOARDED) {
            $status = StudentTimelineMilestoneStatus::Completed;
            $actual = $trip->end_time;
        } elseif ($stop && StudentTripStopStatus::tryFrom((string) $stop->status) === StudentTripStopStatus::BOARDED) {
            $status = StudentTimelineMilestoneStatus::OnWay;
        }

        return $this->milestonePayload($code, $status, $scheduled, $actual);
    }

    private function pickupStatus(?TripHistoryStudent $stop, bool $defaultScheduled = false): StudentTimelineMilestoneStatus
    {
        if (! $stop) {
            return StudentTimelineMilestoneStatus::Scheduled;
        }

        $stopStatus = StudentTripStopStatus::tryFrom((string) $stop->status) ?? StudentTripStopStatus::IDLE;

        return match ($stopStatus) {
            StudentTripStopStatus::ABSENT => StudentTimelineMilestoneStatus::Absent,
            StudentTripStopStatus::BOARDED => StudentTimelineMilestoneStatus::Boarded,
            StudentTripStopStatus::ARRIVED => StudentTimelineMilestoneStatus::Arrived,
            StudentTripStopStatus::ON_WAY => StudentTimelineMilestoneStatus::OnWay,
            default => $defaultScheduled ? StudentTimelineMilestoneStatus::Scheduled : StudentTimelineMilestoneStatus::Scheduled,
        };
    }

    private function tripCompleted(TripHistory $trip): bool
    {
        $status = strtoupper((string) $trip->status);

        return in_array($status, ['PRESENT', 'COMPLETED', 'DONE'], true)
            || ($trip->end_time !== null && $trip->end_time->isPast());
    }

    /**
     * @return array<string, mixed>
     */
    private function milestonePayload(
        StudentTimelineMilestoneCode $code,
        StudentTimelineMilestoneStatus $status,
        Carbon $scheduledAt,
        mixed $actualAt = null,
        ?string $descriptionOverride = null,
    ): array {
        $actualCarbon = $this->asCarbon($actualAt);

        return [
            'code' => $code->value,
            'title_en' => $code->titleEn(),
            'title_ar' => $code->titleAr(),
            'description_en' => $descriptionOverride ?? $code->descriptionEn(),
            'description_ar' => $descriptionOverride ?? $code->descriptionAr(),
            'icon' => $code->icon(),
            'status' => $status->value,
            'status_label_en' => $status->labelEn(),
            'status_label_ar' => $status->labelAr(),
            'status_color' => $status->colorHex(),
            'status_background_color' => $status->backgroundColorHex(),
            'scheduled_at' => $scheduledAt->toIso8601String(),
            'scheduled_time' => $scheduledAt->format('H:i'),
            'scheduled_time_label_ar' => $this->formatTimeArabic($scheduledAt),
            'actual_at' => $actualCarbon?->toIso8601String(),
            'actual_time' => $actualCarbon?->format('H:i'),
            'actual_time_label_ar' => $actualCarbon ? $this->formatTimeArabic($actualCarbon) : null,
        ];
    }

    private function scheduledAt(Carbon $day, ?TripHistory $trip, string $fallbackTime, bool $useEndTime = false): Carbon
    {
        if ($trip) {
            $source = $useEndTime ? ($trip->end_time ?? $trip->start_time) : $trip->start_time;
            if ($source instanceof Carbon) {
                return $source->copy();
            }
        }

        return Carbon::parse($day->toDateString().' '.$fallbackTime);
    }

    private function asCarbon(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value->copy();
        }
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse((string) $value);
    }

    private function formatTimeArabic(Carbon $time): string
    {
        $hour = (int) $time->format('g');
        $minute = $time->format('i');
        $suffix = (int) $time->format('H') < 12 ? 'صباحاً' : 'مساءً';

        return sprintf('%d:%s %s', $hour, $minute, $suffix);
    }

    private function homeDescription(Student $student): string
    {
        $parts = array_filter([
            trim((string) ($student->district_area ?? '')),
            trim((string) ($student->nearest_landmark ?? '')),
        ]);

        if ($parts !== []) {
            return implode(' — ', $parts);
        }

        return StudentTimelineMilestoneCode::MorningPickupHome->descriptionAr();
    }

    /**
     * @return array{id: int, full_name: string, grade: string|null, school_name_en: string|null, school_name_ar: string|null, profile_photo: string|null}
     */
    private function studentHeader(Student $student): array
    {
        return [
            'id' => (int) $student->id,
            'full_name' => (string) $student->full_name,
            'grade' => $student->grade,
            'school_name_en' => $student->school?->name_en,
            'school_name_ar' => $student->school?->name_ar,
            'profile_photo' => $student->profile_photo ? url('storage/'.$student->profile_photo) : null,
        ];
    }

    private function isAbsentOnDate(int $studentId, Carbon $day): bool
    {
        $dayString = $day->toDateString();

        return Absence::query()
            ->where('student_id', $studentId)
            ->whereDate('start_date', '<=', $dayString)
            ->whereDate('end_date', '>=', $dayString)
            ->exists();
    }

    /**
     * @return Collection<int, TripHistoryStudent>
     */
    private function tripRowsForDate(int $studentId, Carbon $day): Collection
    {
        $from = $day->copy()->startOfDay();
        $to = $day->copy()->endOfDay();

        return TripHistoryStudent::query()
            ->where('student_id', $studentId)
            ->whereHas('tripHistory', fn ($q) => $q->whereBetween('start_time', [$from, $to]))
            ->with('tripHistory')
            ->get();
    }

    /**
     * @param  Collection<int, TripHistoryStudent>  $rows
     * @return array<string, array{stop: TripHistoryStudent, trip: TripHistory}>
     */
    private function tripsByType(Collection $rows): array
    {
        $map = [];
        foreach ($rows as $row) {
            $trip = $row->tripHistory;
            if (! $trip) {
                continue;
            }
            $type = (string) ($trip->trip_type ?? '');
            if ($type === '') {
                continue;
            }
            $map[$type] = ['stop' => $row, 'trip' => $trip];
        }

        return $map;
    }

    /**
     * Return leg for milestones 3–4: school pickup → home (MORNING_RETURN or EVENING_RETURN).
     *
     * @return array{stop: TripHistoryStudent, trip: TripHistory}|null
     */
    private function resolveReturnTripContext(
        ?array $morningReturn,
        ?array $eveningReturn,
    ): ?array {
        if ($morningReturn !== null) {
            return $morningReturn;
        }

        if ($eveningReturn !== null) {
            return $eveningReturn;
        }

        return null;
    }
}
