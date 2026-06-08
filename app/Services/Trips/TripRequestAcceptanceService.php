<?php

namespace App\Services\Trips;

use App\Enums\StudentTripStopStatus;
use App\Enums\TripType;
use App\Models\TripHistory;
use App\Models\TripHistoryStudent;
use App\Models\TripRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class TripRequestAcceptanceService
{
    /**
     * Apply accepted / rejected for a pending trip request.
     * Acceptance never creates a new trip — it only links to an existing scheduled trip.
     */
    public function applyDriverDecision(TripRequest $tripRequest, string $status): void
    {
        DB::transaction(function () use ($tripRequest, $status): void {
            $tripRequest->update(['status' => $status]);
            if ($status === 'accepted') {
                $this->attachAcceptedRequestToTrip(
                    $tripRequest->fresh(['student.school', 'driver.bus', 'tripHistory']),
                );
            }
        });
    }

    private function attachAcceptedRequestToTrip(TripRequest $tripRequest): void
    {
        $student = $tripRequest->student;
        if (! $student) {
            throw ValidationException::withMessages([
                'status' => [__('dashboard.trip_request_no_scheduled_trip')],
            ]);
        }

        $trip = $this->resolveTripForAcceptedRequest($tripRequest);
        if ($trip === null) {
            throw ValidationException::withMessages([
                'status' => [__('dashboard.trip_request_no_scheduled_trip')],
            ]);
        }

        $this->ensureStudentOnTrip($tripRequest, $trip);
        $tripRequest->forceFill(['trip_history_id' => $trip->id])->save();
    }

    private function resolveTripForAcceptedRequest(TripRequest $tripRequest): ?TripHistory
    {
        if ($tripRequest->trip_history_id !== null) {
            $linked = TripHistory::query()->find((int) $tripRequest->trip_history_id);
            if ($linked instanceof TripHistory && ! $this->tripIsTerminal($linked)) {
                return $linked;
            }
        }

        if ($tripRequest->tripHistory instanceof TripHistory && ! $this->tripIsTerminal($tripRequest->tripHistory)) {
            return $tripRequest->tripHistory;
        }

        return $this->findOpenTripForRequest($tripRequest);
    }

    private function findOpenTripForRequest(TripRequest $tripRequest): ?TripHistory
    {
        $student = $tripRequest->student;
        $driverId = (int) ($tripRequest->driver_id ?? 0);
        if (! $student || $driverId <= 0) {
            return null;
        }

        $tripType = $this->inferTripTypeFromPresentType($tripRequest->present_type);
        $day = now()->toDateString();

        $query = TripHistory::query()
            ->where('school_id', (int) $student->school_id)
            ->where('driver_id', $driverId)
            ->whereNotIn('status', ['CANCELLED', 'COMPLETED', 'DONE'])
            ->whereDate('start_time', $day);

        if (is_string($tripType) && $tripType !== '') {
            $query->where('trip_type', $tripType);
        }

        return $query
            ->orderByDesc('start_time')
            ->orderByDesc('id')
            ->first();
    }

    private function ensureStudentOnTrip(TripRequest $tripRequest, TripHistory $trip): void
    {
        $student = $tripRequest->student;
        if (! $student) {
            return;
        }

        $studentId = (int) $student->id;

        $alreadyOnTrip = TripHistoryStudent::query()
            ->where('trip_history_id', $trip->id)
            ->where('student_id', $studentId)
            ->exists();

        if ($alreadyOnTrip) {
            return;
        }

        $nextOrder = (int) TripHistoryStudent::query()
            ->where('trip_history_id', $trip->id)
            ->max('sort_order');

        TripHistoryStudent::query()->create([
            'trip_history_id' => $trip->id,
            'student_id' => $studentId,
            'sort_order' => $nextOrder + 1,
            'status' => StudentTripStopStatus::IDLE->value,
        ]);

        $preview = is_array($trip->students_preview) ? $trip->students_preview : [];
        $preview[] = [
            'id' => (string) $studentId,
            'name' => (string) ($student->full_name ?? ''),
        ];

        $trip->update([
            'students_count' => count($preview),
            'students_preview' => $preview,
        ]);
    }

    private function tripIsTerminal(TripHistory $trip): bool
    {
        return in_array(strtoupper((string) $trip->status), ['CANCELLED', 'COMPLETED', 'DONE'], true);
    }

    private function inferTripTypeFromPresentType(?string $presentType): ?string
    {
        if ($presentType === null || trim($presentType) === '') {
            return null;
        }
        $t = mb_strtolower(trim($presentType));
        if (str_contains($t, 'صباح')) {
            return TripType::MORNING_PICKUP->value;
        }
        if (str_contains($t, 'مساء') || str_contains($t, 'مسائي')) {
            return TripType::EVENING_PICKUP->value;
        }

        return null;
    }
}
