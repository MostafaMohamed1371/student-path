<?php

namespace App\Services\Absences;

use App\Models\Driver;
use App\Models\Student;
use App\Models\TransportRoute;
use App\Models\TransportRouteStudent;
use App\Models\TripHistory;
use App\Models\TripHistoryStudent;
use App\Models\TripRequest;
use App\Services\Routes\RouteAssignmentPlanner;
use App\Services\Trips\TripRequestSlotKeyResolver;

/**
 * Resolve the transport driver for a student (route subscription, accepted trip request, or active trip).
 */
final class AbsenceDriverResolver
{
    public function __construct(
        private readonly RouteAssignmentPlanner $routeAssignmentPlanner,
        private readonly TripRequestSlotKeyResolver $tripRequestSlotKeyResolver,
    ) {}

    /**
     * Try to create route subscription from a recent accepted trip request before reporting absence.
     */
    public function backfillSubscriptionIfNeeded(Student $student): void
    {
        $student->loadMissing('transportRouteStudent.transportRoute.driver');

        if ($this->resolveFromTransportRoute($student)['driver'] instanceof Driver) {
            return;
        }

        $tripRequest = $this->findLatestAcceptedTripRequest($student);
        if ($tripRequest === null) {
            return;
        }

        $tripRequest->loadMissing(['driver', 'tripHistory']);
        $driverId = (int) ($tripRequest->driver_id ?? 0);
        $tripType = $this->tripTypeForTripRequest($tripRequest);

        if ($driverId <= 0 || $tripType === null) {
            return;
        }

        $this->routeAssignmentPlanner->ensureStudentSubscribedFromTripAcceptance(
            $student,
            $driverId,
            $tripType,
            $tripRequest->tripHistory,
        );
    }

    /**
     * @return array{driver: Driver|null, transport_route: TransportRoute|null}
     */
    public function resolveForStudent(Student $student): array
    {
        $student->loadMissing('transportRouteStudent.transportRoute.driver');

        $fromRoute = $this->resolveFromTransportRoute($student);
        if ($fromRoute['driver'] instanceof Driver) {
            return $fromRoute;
        }

        $fromTripRequest = $this->resolveFromAcceptedTripRequest($student);
        if ($fromTripRequest['driver'] instanceof Driver) {
            return $fromTripRequest;
        }

        return $this->resolveFromActiveTripRoster($student);
    }

    /**
     * @return array{driver: Driver|null, transport_route: TransportRoute|null}
     */
    private function resolveFromTransportRoute(Student $student): array
    {
        $assignment = $student->transportRouteStudent;
        if (! $assignment instanceof TransportRouteStudent) {
            return ['driver' => null, 'transport_route' => null];
        }

        $route = $assignment->transportRoute;
        if (! $route instanceof TransportRoute || $route->status !== 'active') {
            return ['driver' => null, 'transport_route' => null];
        }

        $driver = $route->driver;
        if (! $driver instanceof Driver || $driver->status !== 'active') {
            return ['driver' => null, 'transport_route' => $route];
        }

        if ((int) $driver->school_id !== (int) $student->school_id) {
            return ['driver' => null, 'transport_route' => $route];
        }

        return ['driver' => $driver, 'transport_route' => $route];
    }

    /**
     * @return array{driver: Driver|null, transport_route: TransportRoute|null}
     */
    private function resolveFromAcceptedTripRequest(Student $student): array
    {
        $tripRequest = $this->findLatestAcceptedTripRequest($student);
        if ($tripRequest === null) {
            return ['driver' => null, 'transport_route' => null];
        }

        $tripRequest->loadMissing(['driver', 'tripHistory']);
        $driver = $tripRequest->driver;
        if (! $this->driverEligibleForStudent($driver, $student)) {
            return ['driver' => null, 'transport_route' => null];
        }

        $route = null;
        $freshStudent = $student->fresh(['transportRouteStudent.transportRoute']);
        if ($freshStudent instanceof Student) {
            $assignment = $freshStudent->transportRouteStudent;
            if ($assignment instanceof TransportRouteStudent) {
                $candidate = $assignment->transportRoute;
                if ($candidate instanceof TransportRoute
                    && $candidate->status === 'active'
                    && (int) $candidate->driver_id === (int) $driver->id) {
                    $route = $candidate;
                }
            }
        }

        return ['driver' => $driver, 'transport_route' => $route];
    }

    /**
     * @return array{driver: Driver|null, transport_route: TransportRoute|null}
     */
    private function resolveFromActiveTripRoster(Student $student): array
    {
        $rosterRow = TripHistoryStudent::query()
            ->where('student_id', (int) $student->id)
            ->whereHas('tripHistory', function ($query) use ($student): void {
                $query
                    ->where('school_id', (int) $student->school_id)
                    ->whereNotIn('status', ['CANCELLED', 'COMPLETED', 'DONE'])
                    ->whereDate('start_time', now()->toDateString());
            })
            ->with(['tripHistory.driver'])
            ->latest('id')
            ->first();

        if ($rosterRow === null) {
            return ['driver' => null, 'transport_route' => null];
        }

        $trip = $rosterRow->tripHistory;
        if (! $trip instanceof TripHistory) {
            return ['driver' => null, 'transport_route' => null];
        }

        $driver = $trip->driver;
        if (! $this->driverEligibleForStudent($driver, $student)) {
            return ['driver' => null, 'transport_route' => null];
        }

        return ['driver' => $driver, 'transport_route' => null];
    }

    private function findLatestAcceptedTripRequest(Student $student): ?TripRequest
    {
        return TripRequest::query()
            ->where('student_id', (int) $student->id)
            ->where('status', 'accepted')
            ->with(['driver', 'tripHistory'])
            ->latest('id')
            ->get()
            ->first(function (TripRequest $request) use ($student): bool {
                return $this->driverEligibleForStudent($request->driver, $student);
            });
    }

    private function tripTypeForTripRequest(TripRequest $tripRequest): ?string
    {
        $tripRequest->loadMissing('tripHistory');

        $fromTrip = trim((string) ($tripRequest->tripHistory?->trip_type ?? ''));
        if ($fromTrip !== '') {
            return $fromTrip;
        }

        return $this->tripRequestSlotKeyResolver->slotKeyForRequest($tripRequest);
    }

    private function driverEligibleForStudent(?Driver $driver, Student $student): bool
    {
        return $driver instanceof Driver
            && $driver->status === 'active'
            && (int) $driver->school_id === (int) $student->school_id;
    }
}
