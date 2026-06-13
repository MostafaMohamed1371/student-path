<?php

namespace App\Services\Trips;

use App\Enums\StudentTripStopStatus;
use App\Models\Student;
use App\Models\TripHistory;
use App\Models\TripHistoryStudent;
use App\Models\TripRequest;
use App\Services\Routes\RouteAssignmentPlanner;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class TripRequestAcceptanceService
{
    public function __construct(
        private readonly TripRequestConflictGuard $conflictGuard,
        private readonly TripRequestSlotKeyResolver $slotKeyResolver,
        private readonly TripRequestNotificationService $notificationService,
        private readonly RouteAssignmentPlanner $routeAssignmentPlanner,
    ) {}

    /**
     * Apply accepted / rejected for a pending trip request.
     * Acceptance never creates a new trip — it only links to an existing scheduled trip.
     */
    public function applyDriverDecision(TripRequest $tripRequest, string $status): void
    {
        if ($status === 'accepted') {
            $tripRequest->loadMissing(['student.school', 'driver.bus', 'tripHistory']);
            $slotKey = $this->resolveAcceptanceSlotKey($tripRequest);
            if ($this->conflictGuard->slotTakenByAnotherDriver($tripRequest, $slotKey)) {
                $this->conflictGuard->closePendingRequestWhenSlotTaken($tripRequest, $slotKey);

                throw ValidationException::withMessages([
                    'status' => [__('dashboard.trip_request_slot_taken_by_another_driver')],
                ]);
            }
        }

        DB::transaction(function () use ($tripRequest, $status): void {
            if ($status === 'accepted' && $tripRequest->student_id !== null) {
                Student::query()->whereKey($tripRequest->student_id)->lockForUpdate()->first();
            }

            $locked = TripRequest::query()->whereKey($tripRequest->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages([
                    'status' => [__('dashboard.trip_request_only_pending_status')],
                ]);
            }

            if ($status === 'accepted') {
                $locked->loadMissing(['student.school', 'driver.bus', 'tripHistory']);

                $slotKey = $this->resolveAcceptanceSlotKey($locked);
                $this->conflictGuard->assertCanAcceptRequest($locked, $slotKey);
            }

            $locked->update(['status' => $status]);

            if ($status === 'accepted') {
                $accepted = $locked->fresh(['student.school', 'driver.bus', 'tripHistory']);
                $trip = $this->attachAcceptedRequestToTrip($accepted);
                $this->subscribeStudentToDriverRoute($accepted, $trip);
                $slotKey = $this->resolveAcceptanceSlotKey($accepted->fresh(['tripHistory']));
                $this->conflictGuard->rejectCompetingPendingRequests($accepted->fresh(['tripHistory']), $slotKey);
            }

            if (in_array($status, ['accepted', 'rejected'], true)) {
                $this->notificationService->notifyParentOfDriverDecision(
                    $locked->fresh(['user', 'driver', 'student', 'tripHistory']),
                    $status,
                );
            }
        });
    }

    private function attachAcceptedRequestToTrip(TripRequest $tripRequest): TripHistory
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

        return $trip;
    }

    private function subscribeStudentToDriverRoute(TripRequest $tripRequest, TripHistory $trip): void
    {
        $student = $tripRequest->student;
        $driverId = (int) ($tripRequest->driver_id ?? 0);
        $tripType = trim((string) ($trip->trip_type ?? ''));

        if (! $student || $driverId <= 0 || $tripType === '') {
            return;
        }

        $this->routeAssignmentPlanner->ensureStudentSubscribedFromTripAcceptance(
            $student,
            $driverId,
            $tripType,
        );
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
        return app(TripRequestSlotKeyResolver::class)->inferTripTypeFromPresentType($presentType);
    }

    private function resolveAcceptanceSlotKey(TripRequest $tripRequest): ?string
    {
        $slotKey = $this->slotKeyResolver->slotKeyForRequest($tripRequest);
        if ($slotKey !== null) {
            return $slotKey;
        }

        $trip = $this->resolveTripForAcceptedRequest($tripRequest);

        return $trip !== null
            ? $this->slotKeyResolver->slotKeyForTripHistoryId((int) $trip->id)
            : null;
    }
}
