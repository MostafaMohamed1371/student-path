<?php

namespace App\Services\Routes;

use App\Models\Driver;
use App\Models\School;
use App\Models\Student;
use App\Models\TransportRoute;
use App\Models\TransportRouteStudent;
use App\Models\TripHistory;
use App\Services\Trips\DriverShiftResolver;
use App\Services\Trips\StudentShiftFilter;
use App\Support\Geo\Haversine;
use App\Support\Geo\RouteCorridor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RouteAssignmentPlanner
{
    public function __construct(
        private readonly StudentShiftFilter $studentShiftFilter,
        private readonly DriverShiftResolver $driverShiftResolver,
    ) {}

    /**
     * Unassigned students whose home lies along the route corridor (start → school), pickup order.
     *
     * @return Collection<int, Student>
     */
    public function studentsMatchingRouteCorridor(TransportRoute $route, bool $unassignedOnly = true): Collection
    {
        $route->loadMissing('school');

        $endpoints = $this->routeCorridorEndpoints($route);
        if ($endpoints === null) {
            return collect();
        }

        $query = Student::query()
            ->where('school_id', $route->school_id)
            ->where('status', 'active')
            ->tap(fn (Builder $q) => $this->studentShiftFilter->applyToStudentQuery($q, (string) $route->trip_type));

        if ($unassignedOnly) {
            $query->whereDoesntHave('transportRouteStudent');
        }

        $candidates = [];
        foreach ($query->orderBy('full_name')->get() as $student) {
            $match = $this->corridorMatchForStudent($student, $endpoints);
            if ($match === null) {
                continue;
            }

            $candidates[] = [
                'student' => $student,
                'projection_t' => $match['projection_t'],
                'distance_meters' => $match['distance_meters'],
            ];
        }

        usort($candidates, function (array $a, array $b): int {
            $byT = $a['projection_t'] <=> $b['projection_t'];
            if ($byT !== 0) {
                return $byT;
            }

            return $a['distance_meters'] <=> $b['distance_meters'];
        });

        return collect($candidates)->pluck('student');
    }

    public function studentMatchesRouteCorridor(Student $student, TransportRoute $route): bool
    {
        $route->loadMissing('school');
        $endpoints = $this->routeCorridorEndpoints($route);
        if ($endpoints === null) {
            return false;
        }

        return $this->corridorMatchForStudent($student, $endpoints) !== null;
    }

    public function pointMatchesRouteCorridor(float $latitude, float $longitude, TransportRoute $route): bool
    {
        $route->loadMissing('school');
        $endpoints = $this->routeCorridorEndpoints($route);
        if ($endpoints === null) {
            return false;
        }

        $corridor = RouteCorridor::pointToSegment(
            $latitude,
            $longitude,
            $endpoints['start_lat'],
            $endpoints['start_lng'],
            $endpoints['end_lat'],
            $endpoints['end_lng'],
        );

        return $corridor['distance_meters'] <= $endpoints['max_meters'];
    }

    public function studentAssignedToRoute(Student $student, TransportRoute $route): bool
    {
        return TransportRouteStudent::query()
            ->where('transport_route_id', $route->id)
            ->where('student_id', $student->id)
            ->exists();
    }

    /**
     * Student may appear on a trip for this driver when home is along the route corridor
     * or the student is already on the driver's transport route.
     */
    public function studentEligibleForDriverRoute(Student $student, TransportRoute $route): bool
    {
        if ($this->studentAssignedToRoute($student, $route)) {
            return true;
        }

        return $this->studentMatchesRouteCorridor($student, $route);
    }

    /**
     * @param  Collection<int, Student>  $students
     * @return Collection<int, Student>
     */
    public function filterStudentsForDriverRoute(Collection $students, TransportRoute $route): Collection
    {
        return $students
            ->filter(fn (Student $student): bool => $this->studentEligibleForDriverRoute($student, $route))
            ->values();
    }

    /**
     * Assign students whose home is along the route corridor (start point → school).
     *
     * @return array{assigned: int, skipped_no_location: int, skipped_off_corridor: int, skipped_no_capacity: int, skipped_no_school_location: int}
     */
    public function assignStudentsAlongRoute(TransportRoute $route, bool $replaceExisting = false): array
    {
        $route->load(['school', 'driver.bus']);

        $empty = [
            'assigned' => 0,
            'skipped_no_location' => 0,
            'skipped_off_corridor' => 0,
            'skipped_no_capacity' => 0,
            'skipped_no_school_location' => 0,
        ];

        if ($route->status !== 'active') {
            return $empty;
        }

        $endpoints = $this->routeCorridorEndpoints($route);
        if ($endpoints === null) {
            return array_merge($empty, ['skipped_no_school_location' => 1]);
        }

        $driver = $route->driver;
        if (! $driver) {
            return $empty;
        }

        $endLat = $endpoints['end_lat'];
        $endLng = $endpoints['end_lng'];
        $tripType = (string) $route->trip_type;

        return DB::transaction(function () use (
            $route,
            $driver,
            $endpoints,
            $endLat,
            $endLng,
            $tripType,
            $replaceExisting,
        ): array {
            if ($replaceExisting) {
                $route->routeStudents()->delete();
            }

            $capacity = max(1, (int) ($driver->bus?->capacity ?? 1));
            $existingCount = $route->routeStudents()->count();
            $slotsLeft = max(0, $capacity - $existingCount);

            if ($slotsLeft === 0) {
                return [
                    'assigned' => 0,
                    'skipped_no_location' => 0,
                    'skipped_off_corridor' => 0,
                    'skipped_no_capacity' => 0,
                    'skipped_no_school_location' => 0,
                ];
            }

            $students = Student::query()
                ->where('school_id', $route->school_id)
                ->where('status', 'active')
                ->tap(fn (Builder $q) => $this->studentShiftFilter->applyToStudentQuery($q, $tripType))
                ->whereDoesntHave('transportRouteStudent')
                ->orderBy('full_name')
                ->get();

            $candidates = [];
            $skippedNoLocation = 0;
            $skippedOffCorridor = 0;

            foreach ($students as $student) {
                if (! $this->studentHasCoordinates($student)) {
                    $skippedNoLocation++;

                    continue;
                }

                $match = $this->corridorMatchForStudent($student, $endpoints);
                if ($match === null) {
                    $skippedOffCorridor++;

                    continue;
                }

                $candidates[] = [
                    'student' => $student,
                    'projection_t' => $match['projection_t'],
                    'distance_meters' => $match['distance_meters'],
                ];
            }

            usort($candidates, function (array $a, array $b): int {
                $byT = $a['projection_t'] <=> $b['projection_t'];
                if ($byT !== 0) {
                    return $byT;
                }

                return $a['distance_meters'] <=> $b['distance_meters'];
            });

            $assigned = 0;
            $sortOrder = $existingCount;

            foreach ($candidates as $candidate) {
                if ($assigned >= $slotsLeft) {
                    break;
                }

                /** @var Student $student */
                $student = $candidate['student'];

                $distanceKm = Haversine::metersBetween(
                    (float) $student->latitude,
                    (float) $student->longitude,
                    $endLat,
                    $endLng,
                ) / 1000;

                TransportRouteStudent::query()->create([
                    'transport_route_id' => $route->id,
                    'student_id' => $student->id,
                    'sort_order' => $sortOrder,
                    'distance_from_school_km' => $distanceKm,
                ]);

                $sortOrder++;
                $assigned++;
            }

            $this->syncDriverMetaFromRoute($driver, $route->fresh() ?? $route);

            $skippedNoCapacity = max(0, count($candidates) - $assigned);

            return [
                'assigned' => $assigned,
                'skipped_no_location' => $skippedNoLocation,
                'skipped_off_corridor' => $skippedOffCorridor,
                'skipped_no_capacity' => $skippedNoCapacity,
                'skipped_no_school_location' => 0,
            ];
        });
    }

    /**
     * @return array{assigned: int, skipped_no_location: int, skipped_off_corridor: int, skipped_no_capacity: int, routes_touched: int}
     */
    public function autoAssignForSchoolTripType(
        int $schoolId,
        string $tripType,
        bool $clearExisting = false,
    ): array {
        $school = School::query()->find($schoolId);
        $tripType = trim($tripType);
        if (! $school || $tripType === '') {
            return [
                'assigned' => 0,
                'skipped_no_location' => 0,
                'skipped_off_corridor' => 0,
                'skipped_no_capacity' => 0,
                'routes_touched' => 0,
            ];
        }

        return DB::transaction(function () use ($school, $tripType, $clearExisting): array {
            if ($clearExisting) {
                $routeIds = TransportRoute::query()
                    ->where('school_id', $school->id)
                    ->where('trip_type', $tripType)
                    ->pluck('id');
                TransportRouteStudent::query()->whereIn('transport_route_id', $routeIds)->delete();
            }

            $routeRows = TransportRoute::query()
                ->with(['driver.bus', 'school'])
                ->where('school_id', $school->id)
                ->where('trip_type', $tripType)
                ->where('status', 'active')
                ->whereNotNull('start_latitude')
                ->whereNotNull('start_longitude')
                ->get();

            if ($routeRows->isEmpty()) {
                return [
                    'assigned' => 0,
                    'skipped_no_location' => 0,
                    'skipped_off_corridor' => 0,
                    'skipped_no_capacity' => 0,
                    'routes_touched' => 0,
                ];
            }

            $assignedTotal = 0;
            $skippedNoLocation = 0;
            $skippedOffCorridor = 0;
            $skippedNoCapacity = 0;
            $routesTouched = 0;

            foreach ($routeRows as $route) {
                $result = $this->assignStudentsAlongRoute($route, replaceExisting: false);

                if ($result['assigned'] > 0) {
                    $routesTouched++;
                }

                $assignedTotal += $result['assigned'];
                $skippedNoLocation += $result['skipped_no_location'];
                $skippedOffCorridor += $result['skipped_off_corridor'];
                $skippedNoCapacity += $result['skipped_no_capacity'];
            }

            return [
                'assigned' => $assignedTotal,
                'skipped_no_location' => $skippedNoLocation,
                'skipped_off_corridor' => $skippedOffCorridor,
                'skipped_no_capacity' => $skippedNoCapacity,
                'routes_touched' => $routesTouched,
            ];
        });
    }

    public function assignStudentToDriverRoute(
        int $schoolId,
        int $driverId,
        string $tripType,
        int $studentId,
    ): TransportRouteStudent {
        $tripType = trim($tripType);
        $shift = $this->driverShiftResolver->fromTripType($tripType) ?? DriverShiftResolver::MORNING;

        return DB::transaction(function () use ($schoolId, $driverId, $tripType, $shift, $studentId): TransportRouteStudent {
            $student = Student::query()
                ->whereKey($studentId)
                ->where('school_id', $schoolId)
                ->where('status', 'active')
                ->firstOrFail();

            if (! $this->studentShiftFilter->studentMatchesTripType($student, $tripType)) {
                throw ValidationException::withMessages([
                    'student_id' => [__('dashboard.route_shift_mismatch')],
                ]);
            }

            if (TransportRouteStudent::query()->where('student_id', $student->id)->exists()) {
                throw ValidationException::withMessages([
                    'student_id' => [__('dashboard.route_student_already_assigned')],
                ]);
            }

            $route = TransportRoute::query()
                ->with('school')
                ->where('driver_id', $driverId)
                ->where('trip_type', $tripType)
                ->where('school_id', $schoolId)
                ->where('status', 'active')
                ->first();

            if ($route === null) {
                throw ValidationException::withMessages([
                    'driver_id' => [__('dashboard.route_driver_has_no_route')],
                ]);
            }

            if (! $this->studentMatchesRouteCorridor($student, $route)) {
                throw ValidationException::withMessages([
                    'student_id' => [__('dashboard.route_student_off_corridor')],
                ]);
            }

            $driver = Driver::query()->with('bus')->findOrFail($driverId);
            $capacity = max(1, (int) ($driver->bus?->capacity ?? 1));
            if ($route->routeStudents()->count() >= $capacity) {
                throw ValidationException::withMessages([
                    'driver_id' => [__('dashboard.route_driver_bus_full')],
                ]);
            }

            $school = School::query()->find($schoolId);
            $distanceKm = null;
            if (
                $school
                && $school->latitude !== null
                && $school->longitude !== null
                && $this->studentHasCoordinates($student)
            ) {
                $distanceKm = Haversine::metersBetween(
                    (float) $student->latitude,
                    (float) $student->longitude,
                    (float) $school->latitude,
                    (float) $school->longitude,
                ) / 1000;
            }

            $row = TransportRouteStudent::query()->create([
                'transport_route_id' => $route->id,
                'student_id' => $student->id,
                'sort_order' => $route->routeStudents()->count(),
                'distance_from_school_km' => $distanceKm,
            ]);

            $this->syncDriverMetaFromRoute($driver, $route);

            return $row;
        });
    }

    /**
     * @return array{start_lat: float, start_lng: float, end_lat: float, end_lng: float, max_meters: float}|null
     */
    private function routeCorridorEndpoints(TransportRoute $route): ?array
    {
        $school = $route->school;
        if (
            ! $school
            || $school->latitude === null
            || $school->longitude === null
            || $route->start_latitude === null
            || $route->start_longitude === null
        ) {
            return null;
        }

        return [
            'start_lat' => (float) $route->start_latitude,
            'start_lng' => (float) $route->start_longitude,
            'end_lat' => (float) $school->latitude,
            'end_lng' => (float) $school->longitude,
            'max_meters' => (float) config('routes.corridor_max_meters', 3000),
        ];
    }

    /**
     * @param  array{start_lat: float, start_lng: float, end_lat: float, end_lng: float, max_meters: float}  $endpoints
     * @return array{projection_t: float, distance_meters: float}|null
     */
    private function corridorMatchForStudent(Student $student, array $endpoints): ?array
    {
        if (! $this->studentHasCoordinates($student)) {
            return null;
        }

        $corridor = RouteCorridor::pointToSegment(
            (float) $student->latitude,
            (float) $student->longitude,
            $endpoints['start_lat'],
            $endpoints['start_lng'],
            $endpoints['end_lat'],
            $endpoints['end_lng'],
        );

        if ($corridor['distance_meters'] > $endpoints['max_meters']) {
            return null;
        }

        return $corridor;
    }

    private function studentHasCoordinates(?Student $student): bool
    {
        if (! $student) {
            return false;
        }

        return $student->latitude !== null
            && $student->longitude !== null
            && ! ((float) $student->latitude === 0.0 && (float) $student->longitude === 0.0);
    }

    /**
     * Link a student to the accepting driver's active transport route after trip request acceptance.
     * Best-effort: never throws; skips when no matching route exists.
     */
    public function ensureStudentSubscribedFromTripAcceptance(
        Student $student,
        int $driverId,
        string $tripType,
        ?TripHistory $trip = null,
    ): ?TransportRouteStudent {
        $tripType = trim($tripType);
        if ($tripType === '' || $driverId <= 0) {
            return null;
        }

        $student->loadMissing('transportRouteStudent');

        $route = $this->findOrCreateActiveRouteForDriverTrip($student, $driverId, $tripType, $trip);
        if (! $route instanceof TransportRoute) {
            return null;
        }

        $existing = $student->transportRouteStudent;
        if ($existing instanceof TransportRouteStudent
            && (int) $existing->transport_route_id === (int) $route->id) {
            return $existing;
        }

        return DB::transaction(function () use ($student, $driverId, $route, $existing): ?TransportRouteStudent {
            if ($existing instanceof TransportRouteStudent) {
                $existing->delete();
            }

            $driver = Driver::query()->find($driverId);
            if (! $driver instanceof Driver || $driver->status !== 'active') {
                return null;
            }

            $school = $route->school ?? School::query()->find($route->school_id);
            $distanceKm = null;
            if (
                $school
                && $school->latitude !== null
                && $school->longitude !== null
                && $this->studentHasCoordinates($student)
            ) {
                $distanceKm = Haversine::metersBetween(
                    (float) $student->latitude,
                    (float) $student->longitude,
                    (float) $school->latitude,
                    (float) $school->longitude,
                ) / 1000;
            }

            $row = TransportRouteStudent::query()->create([
                'transport_route_id' => $route->id,
                'student_id' => $student->id,
                'sort_order' => $route->routeStudents()->count(),
                'distance_from_school_km' => $distanceKm,
            ]);

            $this->syncDriverMetaFromRoute($driver, $route->fresh() ?? $route);

            return $row;
        });
    }

    /**
     * Copy route subscription/shift and line description onto the assigned driver.
     */
    public function syncDriverMetaFromRoute(Driver $driver, TransportRoute $route): void
    {
        $route->loadMissing(['routeStudents.student']);

        $areas = $route->routeStudents
            ->map(fn (TransportRouteStudent $row): string => trim((string) ($row->student?->district_area ?? '')))
            ->filter()
            ->unique()
            ->values()
            ->take(5)
            ->all();

        $description = $route->name;
        if ($route->start_address) {
            $description .= ' — '.$route->start_address;
        }
        if ($areas !== []) {
            $description .= ' ('.implode(', ', $areas).')';
        }

        $driver->forceFill([
            'route_description' => $description,
            'shift_period' => $route->shift_period,
            'monthly_subscription_price' => $route->monthly_subscription_price,
        ])->save();
    }

    private function findOrCreateActiveRouteForDriverTrip(
        Student $student,
        int $driverId,
        string $tripType,
        ?TripHistory $trip,
    ): ?TransportRoute {
        $route = TransportRoute::query()
            ->with('school')
            ->where('driver_id', $driverId)
            ->where('school_id', (int) $student->school_id)
            ->where('trip_type', $tripType)
            ->where('status', 'active')
            ->first();

        if ($route instanceof TransportRoute) {
            return $route;
        }

        $route = TransportRoute::query()
            ->where('driver_id', $driverId)
            ->where('school_id', (int) $student->school_id)
            ->where('status', 'active')
            ->orderByDesc('id')
            ->first();

        if ($route instanceof TransportRoute) {
            return $route;
        }

        if (! $trip instanceof TripHistory) {
            return null;
        }

        $driver = Driver::query()->find($driverId);
        if (! $driver instanceof Driver || $driver->status !== 'active') {
            return null;
        }

        $shift = $this->driverShiftResolver->fromTripType($tripType) ?? $driver->shift_period;

        return TransportRoute::query()->create([
            'school_id' => (int) $student->school_id,
            'driver_id' => $driverId,
            'name' => trim((string) ($trip->route_title ?? '')) !== ''
                ? trim((string) $trip->route_title)
                : 'Route '.$tripType,
            'trip_type' => $tripType,
            'shift_period' => $shift,
            'start_address' => $trip->location,
            'status' => 'active',
        ]);
    }
}
