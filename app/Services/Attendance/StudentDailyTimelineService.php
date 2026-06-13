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
use App\Models\TripRequest;
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
        $tripsByType = $this->resolveTripsByTypeForStudent($student, $day);

        [$pickupType, $returnType] = $this->pickupReturnTypesForStudent($student);
        $pickupTrip = $tripsByType[$pickupType] ?? null;
        $returnTrip = $tripsByType[$returnType] ?? null;

        $milestones = [
            $this->buildMorningPickupHome($student, $pickupTrip, $isAbsent),
            $this->buildMorningArriveSchool($pickupTrip, $isAbsent),
            $this->buildEveningPickupSchool($returnTrip, $isAbsent),
            $this->buildEveningArriveHome($returnTrip, $isAbsent),
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
     * @return array<string, mixed>
     */
    private function buildMorningPickupHome(
        Student $student,
        ?array $context,
        bool $isAbsent,
    ): array {
        $code = StudentTimelineMilestoneCode::MorningPickupHome;
        $trip = $context !== null ? ($context['trip'] ?? null) : null;
        $scheduled = $this->scheduledAt($trip, useEndTime: false);

        if ($isAbsent) {
            return $this->milestonePayload($code, StudentTimelineMilestoneStatus::Absent, $scheduled, null, $this->homeDescription($student));
        }

        $stop = $context !== null ? ($context['stop'] ?? null) : null;
        $status = $this->pickupStatus($stop);
        $actual = $stop?->boarding_time ?? ($status === StudentTimelineMilestoneStatus::Arrived ? $stop?->arrived_at : null);

        return $this->milestonePayload($code, $status, $scheduled, $actual, $this->homeDescription($student));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildMorningArriveSchool(
        ?array $context,
        bool $isAbsent,
    ): array {
        $code = StudentTimelineMilestoneCode::MorningArriveSchool;
        $trip = $context !== null ? ($context['trip'] ?? null) : null;
        $scheduled = $this->scheduledAt($trip, useEndTime: true);

        if ($isAbsent) {
            return $this->milestonePayload($code, StudentTimelineMilestoneStatus::Absent, $scheduled, null);
        }

        $stop = $context !== null ? ($context['stop'] ?? null) : null;
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
     * @return array<string, mixed>
     */
    private function buildEveningPickupSchool(
        ?array $context,
        bool $isAbsent,
    ): array {
        $code = StudentTimelineMilestoneCode::EveningPickupSchool;
        $trip = $context !== null ? ($context['trip'] ?? null) : null;
        $scheduled = $this->scheduledAt($trip, useEndTime: false);

        if ($isAbsent) {
            return $this->milestonePayload($code, StudentTimelineMilestoneStatus::Absent, $scheduled, null);
        }

        $stop = $context !== null ? ($context['stop'] ?? null) : null;
        $status = $this->pickupStatus($stop, defaultScheduled: true);
        $actual = $stop?->boarding_time;

        return $this->milestonePayload($code, $status, $scheduled, $actual);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildEveningArriveHome(
        ?array $returnTrip,
        bool $isAbsent,
    ): array {
        $code = StudentTimelineMilestoneCode::EveningArriveHome;
        $trip = $returnTrip !== null ? ($returnTrip['trip'] ?? null) : null;
        $stop = $returnTrip !== null ? ($returnTrip['stop'] ?? null) : null;
        $scheduled = $this->scheduledAt($trip, useEndTime: true);

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
        ?Carbon $scheduledAt,
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
            'scheduled_at' => $scheduledAt?->toIso8601String(),
            'scheduled_time' => $scheduledAt?->format('H:i'),
            'scheduled_time_label_ar' => $scheduledAt ? $this->formatTimeArabic($scheduledAt) : null,
            'actual_at' => $actualCarbon?->toIso8601String(),
            'actual_time' => $actualCarbon?->format('H:i'),
            'actual_time_label_ar' => $actualCarbon ? $this->formatTimeArabic($actualCarbon) : null,
        ];
    }

    private function scheduledAt(?TripHistory $trip, bool $useEndTime = false): ?Carbon
    {
        if (! $trip) {
            return null;
        }

        $source = $useEndTime ? ($trip->end_time ?? $trip->start_time) : $trip->start_time;

        return $source instanceof Carbon ? $source->copy() : null;
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
     * @return array{0: string, 1: string} pickup and return trip_type values
     */
    private function pickupReturnTypesForStudent(Student $student): array
    {
        if (strtoupper(trim((string) ($student->shift_period ?? ''))) === 'EVENING') {
            return [TripType::EVENING_PICKUP->value, TripType::EVENING_RETURN->value];
        }

        return [TripType::MORNING_PICKUP->value, TripType::MORNING_RETURN->value];
    }

    /**
     * Roster trips first; fill missing legs from the assigned driver's scheduled trips for the day.
     *
     * @return array<string, array{stop: TripHistoryStudent|null, trip: TripHistory}>
     */
    private function resolveTripsByTypeForStudent(Student $student, Carbon $day): array
    {
        $tripRows = $this->tripRowsForDate((int) $student->id, $day);
        $tripsByType = $this->tripsByType($tripRows);

        $driverId = $this->resolveDriverIdForStudent($student);
        if ($driverId === null) {
            return $tripsByType;
        }

        foreach ($this->driverTripsForDate($driverId, (int) $student->school_id, $day) as $tripType => $trip) {
            if (isset($tripsByType[$tripType])) {
                continue;
            }

            $tripsByType[$tripType] = [
                'stop' => null,
                'trip' => $trip,
            ];
        }

        return $tripsByType;
    }

    private function resolveDriverIdForStudent(Student $student): ?int
    {
        $student->loadMissing('transportRouteStudent.transportRoute');

        $fromRoute = (int) ($student->transportRouteStudent?->transportRoute?->driver_id ?? 0);
        if ($fromRoute > 0) {
            return $fromRoute;
        }

        $fromRequest = TripRequest::query()
            ->where('student_id', (int) $student->id)
            ->where('status', 'accepted')
            ->whereNotNull('driver_id')
            ->latest('id')
            ->value('driver_id');

        if (is_numeric($fromRequest) && (int) $fromRequest > 0) {
            return (int) $fromRequest;
        }

        $fromRoster = TripHistoryStudent::query()
            ->where('student_id', (int) $student->id)
            ->whereHas('tripHistory', function ($query) use ($student): void {
                $query
                    ->where('school_id', (int) $student->school_id)
                    ->whereNotNull('driver_id')
                    ->whereNotIn('status', ['CANCELLED']);
            })
            ->with('tripHistory')
            ->latest('id')
            ->first()
            ?->tripHistory
            ?->driver_id;

        return is_numeric($fromRoster) && (int) $fromRoster > 0 ? (int) $fromRoster : null;
    }

    /**
     * @return array<string, TripHistory>
     */
    private function driverTripsForDate(int $driverId, int $schoolId, Carbon $day): array
    {
        $from = $day->copy()->startOfDay();
        $to = $day->copy()->endOfDay();

        $map = [];
        $trips = TripHistory::query()
            ->where('driver_id', $driverId)
            ->where('school_id', $schoolId)
            ->whereBetween('start_time', [$from, $to])
            ->whereNotIn('status', ['CANCELLED'])
            ->orderBy('start_time')
            ->orderBy('id')
            ->get();

        foreach ($trips as $trip) {
            $type = trim((string) ($trip->trip_type ?? ''));
            if ($type === '' || isset($map[$type])) {
                continue;
            }

            $map[$type] = $trip;
        }

        foreach ($this->driverRecurringTemplates($driverId, $schoolId) as $template) {
            $type = trim((string) ($template->trip_type ?? ''));
            if ($type === '' || isset($map[$type])) {
                continue;
            }

            $projected = $this->projectTemplateTripToDay($template, $day);
            if ($projected instanceof TripHistory) {
                $map[$type] = $projected;
            }
        }

        return $map;
    }

    /**
     * @return Collection<int, TripHistory>
     */
    private function driverRecurringTemplates(int $driverId, int $schoolId): Collection
    {
        return TripHistory::query()
            ->where('driver_id', $driverId)
            ->where('school_id', $schoolId)
            ->where('is_recurring_template', true)
            ->whereNull('recurring_template_id')
            ->whereNotNull('start_time')
            ->whereNotIn('status', ['CANCELLED'])
            ->orderBy('start_time')
            ->orderBy('id')
            ->get();
    }

    private function projectTemplateTripToDay(TripHistory $template, Carbon $day): ?TripHistory
    {
        $tz = (string) (config('app.timezone') ?: 'UTC');
        $targetDay = $day->copy()->timezone($tz)->startOfDay();

        $projected = $template->replicate();
        $projected->start_time = $this->shiftDateTimeToDay($template->start_time, $targetDay, $tz);
        $projected->end_time = $template->end_time !== null
            ? $this->shiftDateTimeToDay($template->end_time, $targetDay, $tz)
            : null;

        return $projected;
    }

    private function shiftDateTimeToDay(mixed $source, Carbon $targetDay, string $tz): Carbon
    {
        $sourceCarbon = $source instanceof Carbon
            ? $source->copy()->timezone($tz)
            : Carbon::parse((string) $source, $tz);

        return $targetDay->copy()->setTime(
            (int) $sourceCarbon->format('H'),
            (int) $sourceCarbon->format('i'),
            (int) $sourceCarbon->format('s'),
        );
    }

    /**
     * @param  Collection<int, TripHistoryStudent>  $rows
     * @return array<string, array{stop: TripHistoryStudent|null, trip: TripHistory}>
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
}
