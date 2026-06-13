<?php

namespace App\Support;

use App\Enums\TripType;
use App\Models\School;
use Illuminate\Support\Carbon;

final class SchoolWorkSchedule
{
    /**
     * Whether the school is open on the given calendar day per {@see School::$work_days}.
     * If no work days are configured, every day is treated as open.
     */
    public function isOpenOn(School $school, Carbon $day): bool
    {
        $workDays = $school->work_days;
        if (! is_array($workDays) || $workDays === []) {
            return true;
        }

        $dayKey = strtolower($day->copy()->locale('en')->dayName);

        return in_array($dayKey, $workDays, true);
    }

    /**
     * When a pickup trip ends at school, the paired return trip starts at school dismissal for that shift.
     */
    public function dismissalTimeForPickupReturn(School $school, TripType $pickupType, Carbon $tripDay): ?Carbon
    {
        $timeValue = match ($pickupType) {
            TripType::MORNING_PICKUP => $school->work_time_to,
            TripType::EVENING_PICKUP => $this->eveningDismissalTime($school),
            default => null,
        };

        return $this->timeOnDay($tripDay, $timeValue);
    }

    private function eveningDismissalTime(School $school): mixed
    {
        $shift = strtoupper(trim((string) ($school->shift_period ?? '')));

        if ($shift === 'BOTH' && filled($school->evening_work_time_to ?? null)) {
            return $school->evening_work_time_to;
        }

        return $school->work_time_to;
    }

    private function timeOnDay(Carbon $day, mixed $timeValue): ?Carbon
    {
        $timeValue = trim((string) ($timeValue ?? ''));
        if ($timeValue === '') {
            return null;
        }

        try {
            $parsed = Carbon::parse($timeValue);

            return $day->copy()->setTime(
                (int) $parsed->format('H'),
                (int) $parsed->format('i'),
                (int) $parsed->format('s'),
            );
        } catch (\Throwable) {
            return null;
        }
    }
}