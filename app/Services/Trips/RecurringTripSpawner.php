<?php

namespace App\Services\Trips;

use App\Enums\StudentTripStopStatus;
use App\Models\School;
use App\Models\TripDriverReplacement;
use App\Models\TripHistory;
use App\Models\TripHistoryStudent;
use App\Support\SchoolWorkSchedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * When staff assign students to a trip, that trip becomes a recurring template.
 * This service clones it on each school working day (same driver, route, times, students).
 */
final class RecurringTripSpawner
{
    public function __construct(
        private readonly SchoolWorkSchedule $schoolWorkSchedule,
        private readonly TripDriverReplacementService $driverReplacements,
    ) {}

    public function registerTemplateFromTrip(TripHistory $trip): void
    {
        $trip->loadMissing(['school', 'tripHistoryStudents']);

        if ((int) ($trip->students_count ?? 0) <= 0 && $trip->tripHistoryStudents->isEmpty()) {
            if ($trip->is_recurring_template) {
                $trip->update(['is_recurring_template' => false]);
            }

            return;
        }

        if (! $trip->is_recurring_template) {
            $trip->update(['is_recurring_template' => true]);
        }
    }

    public function unregisterTemplate(TripHistory $trip): void
    {
        if ($trip->is_recurring_template) {
            $trip->update(['is_recurring_template' => false]);
        }
    }

    /**
     * Spawn missing daily instances for all active templates on a calendar day.
     */
    public function spawnAllForDay(?Carbon $day = null): int
    {
        $day ??= now();
        $created = 0;

        $templates = TripHistory::query()
            ->where('is_recurring_template', true)
            ->whereNull('recurring_template_id')
            ->orderBy('id')
            ->get();

        foreach ($templates as $template) {
            $spawned = $this->spawnTemplateForDay($template, $day);
            if ($spawned instanceof TripHistory && (int) $spawned->recurring_template_id === (int) $template->id) {
                $created++;
            }
        }

        return $created;
    }

    public function spawnAheadForTemplate(TripHistory $template, ?int $horizonDays = null): int
    {
        $template->loadMissing(['school', 'tripHistoryStudents']);
        if (! $template->is_recurring_template || $template->start_time === null) {
            return 0;
        }

        $horizonDays ??= (int) config('trips.recurring_spawn_horizon_days', 30);
        $horizonDays = max(1, min(90, $horizonDays));

        $tz = (string) (config('app.timezone') ?: 'UTC');
        $cursor = now($tz)->startOfDay();
        $created = 0;

        for ($offset = 0; $offset < $horizonDays; $offset++) {
            $day = $cursor->copy()->addDays($offset);
            $spawned = $this->spawnTemplateForDay($template, $day);
            if ($spawned instanceof TripHistory && (int) $spawned->recurring_template_id === (int) $template->id) {
                $created++;
            }
        }

        return $created;
    }

    public function spawnTemplateForDay(TripHistory $template, Carbon $day): ?TripHistory
    {
        $template->loadMissing(['school', 'tripHistoryStudents']);

        if (! $template->is_recurring_template || $template->start_time === null) {
            return null;
        }

        $school = $template->school;
        if (! $school instanceof School) {
            return null;
        }

        $tz = (string) (config('app.timezone') ?: 'UTC');
        $targetDay = $day->copy()->timezone($tz)->startOfDay();

        if (! $this->schoolWorkSchedule->isOpenOn($school, $targetDay)) {
            return null;
        }

        if ($this->isTemplateAnchorDay($template, $targetDay, $tz)) {
            return $template;
        }

        if ($this->instanceExistsForDay($template, $targetDay, $tz)) {
            return null;
        }

        return DB::transaction(function () use ($template, $targetDay, $tz): TripHistory {
            $trip = TripHistory::query()->create($this->attributesForDay($template, $targetDay, $tz));
            $this->cloneStudentsFromTemplate($template, $trip);

            return $trip;
        });
    }

    private function isTemplateAnchorDay(TripHistory $template, Carbon $targetDay, string $tz): bool
    {
        $anchorDay = $template->start_time instanceof Carbon
            ? $template->start_time->copy()->timezone($tz)->startOfDay()
            : Carbon::parse((string) $template->start_time, $tz)->startOfDay();

        return $anchorDay->equalTo($targetDay);
    }

    private function instanceExistsForDay(TripHistory $template, Carbon $targetDay, string $tz): bool
    {
        $dayStart = $targetDay->copy()->startOfDay();
        $dayEnd = $targetDay->copy()->endOfDay();

        return TripHistory::query()
            ->where('recurring_template_id', $template->id)
            ->whereBetween('start_time', [$dayStart, $dayEnd])
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    private function attributesForDay(TripHistory $template, Carbon $targetDay, string $tz): array
    {
        $startTime = $this->shiftDateTimeToDay($template->start_time, $targetDay, $tz);
        $endTime = $template->end_time !== null
            ? $this->shiftDateTimeToDay($template->end_time, $targetDay, $tz)
            : null;

        $driverId = $this->driverReplacements->effectiveDriverId($template, $targetDay)
            ?? $template->driver_id;

        return [
            'school_id' => $template->school_id,
            'driver_id' => $driverId,
            'trip_type' => $template->trip_type,
            'bus_number' => $template->bus_number,
            'route_title' => $template->route_title,
            'location' => $template->location,
            'start_address' => $template->start_address,
            'start_latitude' => $template->start_latitude,
            'start_longitude' => $template->start_longitude,
            'students_count' => $template->students_count,
            'distance_km' => $template->distance_km,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'status' => $this->initialStatusForSpawn($template),
            'is_recurring_template' => false,
            'recurring_template_id' => $template->id,
            'note' => $template->note,
            'students_preview' => is_array($template->students_preview) ? $template->students_preview : [],
        ];
    }

    private function initialStatusForSpawn(TripHistory $template): string
    {
        return 'PRESENT';
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

    private function cloneStudentsFromTemplate(TripHistory $template, TripHistory $trip): void
    {
        $template->loadMissing('tripHistoryStudents');

        foreach ($template->tripHistoryStudents as $row) {
            TripHistoryStudent::query()->create([
                'trip_history_id' => $trip->id,
                'student_id' => $row->student_id,
                'sort_order' => $row->sort_order,
                'status' => StudentTripStopStatus::IDLE->value,
            ]);
        }
    }
}
