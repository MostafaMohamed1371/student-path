<?php

namespace App\Services\Trips;

use App\Enums\StudentTripStopStatus;
use App\Models\TripHistory;
use App\Models\TripHistoryStudent;
use App\Models\TripRequest;
use Illuminate\Support\Facades\DB;

final class TripRequestAcceptanceService
{
    /**
     * Apply accepted / rejected for a pending driver trip request and create trip history when accepted.
     */
    public function applyDriverDecision(TripRequest $tripRequest, string $status): void
    {
        DB::transaction(function () use ($tripRequest, $status): void {
            $tripRequest->update(['status' => $status]);
            if ($status === 'accepted') {
                $this->createTripHistoryForAcceptedRequest($tripRequest->fresh(['user.homeLocation', 'student.school']));
            }
        });
    }

    private function createTripHistoryForAcceptedRequest(TripRequest $tripRequest): void
    {
        $tripRequest->loadMissing(['driver.bus']);
        $student = $tripRequest->student;
        $user = $tripRequest->user;
        if (! $student || ! $user) {
            return;
        }

        $home = $user->homeLocation;
        $school = $student->school;
        $from = $home?->formatted_address
            ?? (isset($home?->latitude, $home?->longitude) ? ($home->latitude.', '.$home->longitude) : null)
            ?? 'Guardian home';
        $to = $school?->name_en
            ?? $school?->name_ar
            ?? $school?->address
            ?? (isset($school?->latitude, $school?->longitude) ? ($school->latitude.', '.$school->longitude) : null)
            ?? 'School';

        $trip = TripHistory::query()->create([
            'school_id' => $student->school_id,
            'driver_id' => $tripRequest->driver_id,
            'trip_type' => $this->inferTripTypeFromPresentType($tripRequest->present_type),
            'bus_number' => (string) ($tripRequest->driver?->bus?->number ?? ''),
            'route_title' => 'Trip Request #'.$tripRequest->id,
            'location' => 'From '.$from.' to '.$to,
            'students_count' => 1,
            'distance_km' => 0,
            'start_time' => now(),
            'status' => 'PRESENT',
            'note' => $tripRequest->notes,
            'students_preview' => [[
                'id' => (string) $student->id,
                'name' => (string) ($student->full_name ?? ''),
            ]],
        ]);

        TripHistoryStudent::query()->create([
            'trip_history_id' => $trip->id,
            'student_id' => $student->id,
            'sort_order' => 0,
            'status' => StudentTripStopStatus::IDLE->value,
        ]);

        $tripRequest->forceFill(['trip_history_id' => $trip->id])->save();
    }

    private function inferTripTypeFromPresentType(?string $presentType): ?string
    {
        if ($presentType === null || trim($presentType) === '') {
            return null;
        }
        $t = mb_strtolower(trim($presentType));
        if (str_contains($t, 'صباح')) {
            return \App\Enums\TripType::MORNING_PICKUP->value;
        }
        if (str_contains($t, 'مساء') || str_contains($t, 'مسائي')) {
            return \App\Enums\TripType::EVENING_PICKUP->value;
        }

        return null;
    }
}
