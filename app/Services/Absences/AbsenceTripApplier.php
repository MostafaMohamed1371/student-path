<?php

namespace App\Services\Absences;

use App\Enums\StudentTripStopStatus;
use App\Models\Absence;
use App\Models\TripHistory;
use App\Models\TripHistoryStudent;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Mark subscribed students absent on the driver's active trips for covered dates.
 */
final class AbsenceTripApplier
{
    public function apply(Absence $absence): int
    {
        if ($absence->driver_id === null || $absence->driver_id <= 0) {
            return 0;
        }

        $absence->loadMissing('student');
        $studentId = (int) $absence->student_id;
        if ($studentId <= 0) {
            return 0;
        }

        $from = $absence->start_date instanceof CarbonInterface
            ? $absence->start_date->copy()->startOfDay()
            : Carbon::parse((string) $absence->start_date)->startOfDay();
        $to = $absence->end_date instanceof CarbonInterface
            ? $absence->end_date->copy()->endOfDay()
            : Carbon::parse((string) $absence->end_date)->endOfDay();

        $trips = TripHistory::query()
            ->where('driver_id', (int) $absence->driver_id)
            ->whereBetween('start_time', [$from, $to])
            ->whereHas('tripHistoryStudents', fn ($q) => $q->where('student_id', $studentId))
            ->get();

        $updated = 0;
        foreach ($trips as $trip) {
            $updated += $this->markStudentAbsentOnTrip($trip, $studentId);
        }

        return $updated;
    }

    public function isStudentAbsentOnDate(int $studentId, ?CarbonInterface $onDay = null): bool
    {
        $day = ($onDay ?? now())->toDateString();

        return Absence::query()
            ->where('student_id', $studentId)
            ->whereDate('start_date', '<=', $day)
            ->whereDate('end_date', '>=', $day)
            ->exists();
    }

    /**
     * @return Collection<int, Absence>
     */
    public function activeAbsencesForStudentOnDate(int $studentId, ?CarbonInterface $onDay = null): Collection
    {
        $day = ($onDay ?? now())->toDateString();

        return Absence::query()
            ->where('student_id', $studentId)
            ->whereDate('start_date', '<=', $day)
            ->whereDate('end_date', '>=', $day)
            ->latest('id')
            ->get();
    }

    private function markStudentAbsentOnTrip(TripHistory $trip, int $studentId): int
    {
        $row = TripHistoryStudent::query()
            ->where('trip_history_id', $trip->id)
            ->where('student_id', $studentId)
            ->first();

        if ($row === null) {
            return 0;
        }

        $current = StudentTripStopStatus::tryFrom((string) $row->status) ?? StudentTripStopStatus::IDLE;
        if (in_array($current, [StudentTripStopStatus::BOARDED, StudentTripStopStatus::ABSENT], true)) {
            return 0;
        }

        $row->update(['status' => StudentTripStopStatus::ABSENT->value]);

        return 1;
    }
}
