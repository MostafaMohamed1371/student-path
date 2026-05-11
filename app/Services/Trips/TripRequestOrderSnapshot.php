<?php

namespace App\Services\Trips;

use App\Models\Driver;
use App\Models\School;
use App\Models\Student;

final class TripRequestOrderSnapshot
{
    /**
     * Build optional DB columns for trip_requests from student + assigned driver + request overrides.
     *
     * @param  array<string, mixed>  $overrides  optional keys: present_type, moving_point, stop_point, subscribe_price
     * @return array<string, mixed>
     */
    public static function build(Student $student, ?Driver $driver, array $overrides = []): array
    {
        $student->loadMissing('school');
        /** @var School|null $school */
        $school = $student->school;

        $moving = $overrides['moving_point'] ?? null;
        if (! is_string($moving) || trim($moving) === '') {
            $moving = self::defaultMovingPoint($student);
        }

        $stop = $overrides['stop_point'] ?? null;
        if (! is_string($stop) || trim($stop) === '') {
            $stop = $school?->name_ar ?? $school?->name_en ?? $school?->address ?? '';
        }

        $present = $overrides['present_type'] ?? null;
        if ($present !== null && (! is_string($present) || trim($present) === '')) {
            $present = null;
        }

        $subscribe = $overrides['subscribe_price'] ?? null;
        if ($subscribe === null && $driver !== null && $driver->monthly_subscription_price !== null) {
            $subscribe = (float) $driver->monthly_subscription_price;
        } elseif ($subscribe !== null) {
            $subscribe = (float) $subscribe;
        }

        return [
            'present_type' => is_string($present) ? trim($present) : null,
            'moving_point' => trim((string) $moving),
            'stop_point' => trim((string) $stop),
            'subscribe_price' => $subscribe,
        ];
    }

    private static function defaultMovingPoint(Student $student): string
    {
        $parts = array_filter([
            $student->district_area,
            $student->nearest_landmark,
        ], fn ($v): bool => is_string($v) && trim($v) !== '');

        return count($parts) > 0 ? implode(' - ', $parts) : '';
    }
}
