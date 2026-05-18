<?php

namespace App\Services\Trips;

use App\Models\Driver;
use App\Models\School;
use App\Models\TransportRoute;
use App\Support\Geo\Haversine;
use Illuminate\Support\Collection;

final class TripTransportRouteApplier
{
    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function applyRouteToTripAttributes(array $attributes, bool $overwrite = false): array
    {
        $route = $this->findRouteForTrip($attributes);
        if ($route === null) {
            return $attributes;
        }

        $route->loadMissing('school');

        if ($overwrite || trim((string) ($attributes['route_title'] ?? '')) === '') {
            $attributes['route_title'] = $this->routeTitleForTrip($route);
        }
        if ($overwrite || trim((string) ($attributes['location'] ?? '')) === '') {
            $attributes['location'] = $this->locationForTrip($route);
        }

        $distanceKm = $this->routeDistanceKm($route);
        if ($distanceKm !== null && ($overwrite || ! isset($attributes['distance_km']) || (float) $attributes['distance_km'] <= 0)) {
            $attributes['distance_km'] = $distanceKm;
        }

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function findRouteForTrip(array $attributes): ?TransportRoute
    {
        $driverId = (int) ($attributes['driver_id'] ?? 0);
        $schoolId = (int) ($attributes['school_id'] ?? 0);
        $tripType = trim((string) ($attributes['trip_type'] ?? ''));

        if ($driverId <= 0 || $schoolId <= 0 || $tripType === '') {
            return null;
        }

        return TransportRoute::query()
            ->with(['school', 'routeStudents'])
            ->where('driver_id', $driverId)
            ->where('school_id', $schoolId)
            ->where('trip_type', $tripType)
            ->where('status', 'active')
            ->first();
    }

    public function routeTitleForTrip(TransportRoute $route): string
    {
        $name = trim((string) $route->name);
        $start = trim((string) ($route->start_address ?? ''));

        if ($name !== '' && $start !== '') {
            return $name.' — '.$start;
        }

        return $name !== '' ? $name : $start;
    }

    public function locationForTrip(TransportRoute $route): ?string
    {
        $route->loadMissing('school');

        $startLabel = $this->pointLabel(
            trim((string) ($route->start_address ?? '')),
            $route->start_latitude,
            $route->start_longitude,
        );

        $school = $route->school;
        $endLabel = $this->pointLabel(
            $school ? trim((string) ($school->address ?? '')) : '',
            $school?->latitude,
            $school?->longitude,
        );

        if ($startLabel === '' && $endLabel === '') {
            return null;
        }

        if ($startLabel === '') {
            return __('dashboard.trip_location_end_only', ['end' => $endLabel]);
        }

        if ($endLabel === '') {
            return __('dashboard.trip_location_start_only', ['start' => $startLabel]);
        }

        return __('dashboard.trip_location_start_to_end', [
            'start' => $startLabel,
            'end' => $endLabel,
        ]);
    }

    public function routeDistanceKm(TransportRoute $route): ?float
    {
        $route->loadMissing('school');
        $school = $route->school;

        if (
            $route->start_latitude === null
            || $route->start_longitude === null
            || ! $school instanceof School
            || $school->latitude === null
            || $school->longitude === null
        ) {
            return null;
        }

        $meters = Haversine::metersBetween(
            (float) $route->start_latitude,
            (float) $route->start_longitude,
            (float) $school->latitude,
            (float) $school->longitude,
        );

        return round($meters / 1000, 2);
    }

    /**
     * When the trip form sends no students, copy roster from the driver's transport route.
     *
     * @param  list<int|string>  $submittedStudentIds
     * @return list<int>
     */
    public function resolveStudentIdsForTrip(array $tripAttributes, array $submittedStudentIds): array
    {
        $unique = array_values(array_unique(array_map(
            static fn ($v): int => (int) $v,
            $submittedStudentIds,
        )));

        if ($unique !== []) {
            return $unique;
        }

        $route = $this->findRouteForTrip($tripAttributes);
        if ($route === null) {
            return [];
        }

        return $this->studentIdsOnRoute($route);
    }

    /**
     * @return list<int>
     */
    public function studentIdsOnRoute(TransportRoute $route): array
    {
        return $route->routeStudents()
            ->orderBy('sort_order')
            ->pluck('student_id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @return array{
     *     route_title: string|null,
     *     location: string|null,
     *     distance_km: float|null,
     *     transport_route_id: int|null,
     *     route_student_ids: list<int>,
     *     start_address: string|null,
     *     end_address: string|null
     * }|null
     */
    public function driverRouteFormPayload(?TransportRoute $route): ?array
    {
        if ($route === null) {
            return null;
        }

        $route->loadMissing('school');
        $school = $route->school;

        return [
            'route_title' => $this->routeTitleForTrip($route),
            'location' => $this->locationForTrip($route),
            'distance_km' => $this->routeDistanceKm($route),
            'transport_route_id' => (int) $route->id,
            'route_student_ids' => $this->studentIdsOnRoute($route),
            'start_address' => trim((string) ($route->start_address ?? '')) !== ''
                ? trim((string) $route->start_address)
                : null,
            'end_address' => $school && trim((string) ($school->address ?? '')) !== ''
                ? trim((string) $school->address)
                : null,
        ];
    }

    /**
     * @param  Collection<int, Driver>  $drivers
     * @return Collection<int, TransportRoute> keyed by driver_id
     */
    public function routesByDriverId(Collection $drivers, int $schoolId, ?string $tripType): Collection
    {
        if ($drivers->isEmpty() || $schoolId <= 0) {
            return collect();
        }

        $driverIds = $drivers->pluck('id')->filter()->values()->all();
        if ($driverIds === []) {
            return collect();
        }

        $query = TransportRoute::query()
            ->with(['school', 'routeStudents'])
            ->where('school_id', $schoolId)
            ->whereIn('driver_id', $driverIds)
            ->where('status', 'active');

        $tripType = trim((string) ($tripType ?? ''));
        if ($tripType !== '') {
            $query->where('trip_type', $tripType);
        }

        return $query->get()->keyBy('driver_id');
    }

    private function pointLabel(string $address, mixed $lat, mixed $lng): string
    {
        if ($address !== '') {
            return $address;
        }

        if ($lat !== null && $lng !== null) {
            return sprintf('%.5f, %.5f', (float) $lat, (float) $lng);
        }

        return '';
    }
}
