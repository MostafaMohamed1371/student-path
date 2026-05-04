<?php

namespace App\Services\Trips;

use App\Models\Student;
use App\Models\TripHistory;
use Illuminate\Support\Carbon;

final class StudentTripStatusResolver
{
    public function currentTripForStudent(Student $student): ?TripHistory
    {
        if (! $student->school_id) {
            return null;
        }

        $candidates = TripHistory::query()
            ->where('school_id', $student->school_id)
            ->where('status', '!=', 'CANCELLED')
            ->where('start_time', '<=', now())
            ->where(function ($q): void {
                $q->whereNull('end_time')->orWhere('end_time', '>=', now());
            })
            ->orderByDesc('start_time')
            ->get();

        foreach ($candidates as $trip) {
            if ($this->tripIncludesStudent($trip, (int) $student->id)) {
                return $trip;
            }
        }

        return null;
    }

    public function tripIncludesStudent(TripHistory $trip, int $studentId): bool
    {
        return $this->previewContainsStudent($trip, $studentId);
    }

    public function tripPayload(?TripHistory $trip): ?array
    {
        if (! $trip) {
            return null;
        }

        $start = $trip->start_time instanceof Carbon ? $trip->start_time : Carbon::parse((string) $trip->start_time);
        $end = $trip->end_time ? ($trip->end_time instanceof Carbon ? $trip->end_time : Carbon::parse((string) $trip->end_time)) : null;

        return [
            'id' => (string) $trip->id,
            'bus_number' => (string) ($trip->bus_number ?? ''),
            'route_title' => (string) ($trip->route_title ?? ''),
            'location' => (string) ($trip->location ?? ''),
            'status' => (string) ($trip->status ?? ''),
            'start_time' => $start->toIso8601String(),
            'end_time' => $end?->toIso8601String(),
        ];
    }

    private function previewContainsStudent(TripHistory $trip, int $studentId): bool
    {
        $preview = $trip->students_preview;
        if (! is_array($preview)) {
            return false;
        }
        foreach ($preview as $row) {
            if (! is_array($row)) {
                continue;
            }
            if ((string) ($row['id'] ?? '') === (string) $studentId) {
                return true;
            }
        }

        return false;
    }
}
