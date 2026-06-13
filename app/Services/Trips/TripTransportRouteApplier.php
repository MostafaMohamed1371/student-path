<?php

namespace App\Services\Trips;

use App\Enums\TripType;
use App\Models\Driver;
use App\Models\School;
use App\Models\TransportRoute;
use App\Models\TripHistory;
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

    public function pairedPickupTripType(string $tripType): ?string
    {
        return match (TripType::tryFrom(trim($tripType))) {
            TripType::MORNING_RETURN => TripType::MORNING_PICKUP->value,
            TripType::EVENING_RETURN => TripType::EVENING_PICKUP->value,
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function findPickupRouteForReturnTrip(array $attributes): ?TransportRoute
    {
        $pickupType = $this->pairedPickupTripType((string) ($attributes['trip_type'] ?? ''));
        if ($pickupType === null) {
            return null;
        }

        return $this->findRouteForTrip([
            'school_id' => $attributes['school_id'] ?? 0,
            'driver_id' => $attributes['driver_id'] ?? 0,
            'trip_type' => $pickupType,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{
     *     address: string|null,
     *     latitude: float|null,
     *     longitude: float|null
     * }|null
     */
    public function pickupStartPointForReturnTrip(array $attributes): ?array
    {
        $pickupRoute = $this->findPickupRouteForReturnTrip($attributes);
        if ($pickupRoute !== null
            && $pickupRoute->start_latitude !== null
            && $pickupRoute->start_longitude !== null) {
            $address = trim((string) ($pickupRoute->start_address ?? ''));

            return [
                'address' => $address !== '' ? $address : null,
                'latitude' => (float) $pickupRoute->start_latitude,
                'longitude' => (float) $pickupRoute->start_longitude,
            ];
        }

        $pickupType = $this->pairedPickupTripType((string) ($attributes['trip_type'] ?? ''));
        if ($pickupType === null) {
            return null;
        }

        $driverId = (int) ($attributes['driver_id'] ?? 0);
        $schoolId = (int) ($attributes['school_id'] ?? 0);
        if ($driverId <= 0 || $schoolId <= 0) {
            return null;
        }

        $pickupTrip = TripHistory::query()
            ->where('driver_id', $driverId)
            ->where('school_id', $schoolId)
            ->where('trip_type', $pickupType)
            ->whereNotNull('start_latitude')
            ->whereNotNull('start_longitude')
            ->orderByDesc('start_time')
            ->orderByDesc('id')
            ->first();

        if ($pickupTrip === null) {
            return null;
        }

        $address = trim((string) ($pickupTrip->start_address ?? ''));

        return [
            'address' => $address !== '' ? $address : null,
            'latitude' => (float) $pickupTrip->start_latitude,
            'longitude' => (float) $pickupTrip->start_longitude,
        ];
    }

    /**
     * Return trips run school → driver pickup start (reverse of pickup).
     *
     * @return array{
     *     route_title: string|null,
     *     location: string|null,
     *     distance_km: float|null,
     *     transport_route_id: int|null,
     *     route_student_ids: list<int>,
     *     start_address: string|null,
     *     end_address: string|null,
     *     route_start_latitude: float|null,
     *     route_start_longitude: float|null,
     *     route_end_latitude: float|null,
     *     route_end_longitude: float|null,
     *     school_latitude: float|null,
     *     school_longitude: float|null
     * }|null
     */
    public function returnTripFormPayloadFromPickupRoute(TransportRoute $pickupRoute, string $returnTripType): ?array
    {
        $pickupRoute->loadMissing('school');
        $school = $pickupRoute->school;
        if (! $school instanceof School
            || $school->latitude === null
            || $school->longitude === null) {
            return null;
        }

        $pickupStartAddress = trim((string) ($pickupRoute->start_address ?? ''));
        $schoolAddress = trim((string) ($school->address ?? ''));

        if ($pickupRoute->start_latitude === null || $pickupRoute->start_longitude === null) {
            return null;
        }

        $pickupTitle = $this->routeTitleForTrip($pickupRoute);
        $returnType = TripType::tryFrom(trim($returnTripType));

        return [
            'route_title' => $this->returnRouteTitle($pickupTitle, $returnType),
            'location' => $this->returnLocationLabel($schoolAddress, $pickupStartAddress),
            'distance_km' => $this->routeDistanceKm($pickupRoute),
            'transport_route_id' => (int) $pickupRoute->id,
            'route_student_ids' => $this->studentIdsOnRoute($pickupRoute),
            'start_address' => $schoolAddress !== '' ? $schoolAddress : null,
            'end_address' => $pickupStartAddress !== '' ? $pickupStartAddress : null,
            'route_start_latitude' => (float) $school->latitude,
            'route_start_longitude' => (float) $school->longitude,
            'route_end_latitude' => (float) $pickupRoute->start_latitude,
            'route_end_longitude' => (float) $pickupRoute->start_longitude,
            'school_latitude' => (float) $school->latitude,
            'school_longitude' => (float) $school->longitude,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function applyReturnTripPathAttributes(array $attributes, School $school): array
    {
        $pickupStart = $this->pickupStartPointForReturnTrip($attributes);
        $schoolAddress = trim((string) ($school->address ?? ''));

        if ($school->latitude !== null && $school->longitude !== null) {
            $attributes['start_latitude'] = (float) $school->latitude;
            $attributes['start_longitude'] = (float) $school->longitude;
            if ($schoolAddress !== '') {
                $attributes['start_address'] = $schoolAddress;
            }
        }

        $pickupAddress = $pickupStart['address'] ?? '';
        if ($pickupAddress !== '') {
            $attributes['location'] = $this->returnLocationLabel($schoolAddress, $pickupAddress);
        }

        if (
            $pickupStart !== null
            && $school->latitude !== null
            && $school->longitude !== null
        ) {
            $attributes['distance_km'] = round(
                Haversine::metersBetween(
                    (float) $school->latitude,
                    (float) $school->longitude,
                    $pickupStart['latitude'],
                    $pickupStart['longitude'],
                ) / 1000,
                2,
            );
        }

        if (trim((string) ($attributes['route_title'] ?? '')) === '') {
            $pickupRoute = $this->findPickupRouteForReturnTrip($attributes);
            if ($pickupRoute !== null) {
                $returnType = TripType::tryFrom(trim((string) ($attributes['trip_type'] ?? '')));
                $attributes['route_title'] = $this->returnRouteTitle(
                    $this->routeTitleForTrip($pickupRoute),
                    $returnType,
                );
            }
        }

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $tripAttributes
     * @return array{
     *     route_title: string|null,
     *     location: string|null,
     *     distance_km: float|null,
     *     transport_route_id: int|null,
     *     route_student_ids: list<int>,
     *     start_address: string|null,
     *     end_address: string|null,
     *     route_start_latitude: float|null,
     *     route_start_longitude: float|null,
     *     route_end_latitude: float|null,
     *     route_end_longitude: float|null,
     *     school_latitude: float|null,
     *     school_longitude: float|null
     * }|null
     */
    public function returnTripFormPayload(array $tripAttributes, string $returnTripType): ?array
    {
        $pickupRoute = $this->findPickupRouteForReturnTrip([
            ...$tripAttributes,
            'trip_type' => $returnTripType,
        ]);

        if ($pickupRoute !== null) {
            return $this->returnTripFormPayloadFromPickupRoute($pickupRoute, $returnTripType);
        }

        $pickupStart = $this->pickupStartPointForReturnTrip([
            ...$tripAttributes,
            'trip_type' => $returnTripType,
        ]);

        $schoolId = (int) ($tripAttributes['school_id'] ?? 0);
        $school = School::query()->find($schoolId);
        if ($pickupStart === null || ! $school instanceof School
            || $school->latitude === null
            || $school->longitude === null) {
            return null;
        }

        $schoolAddress = trim((string) ($school->address ?? ''));
        $pickupAddress = trim((string) ($pickupStart['address'] ?? ''));

        return [
            'route_title' => $this->returnRouteTitle('', TripType::tryFrom(trim($returnTripType))),
            'location' => $this->returnLocationLabel($schoolAddress, $pickupAddress),
            'distance_km' => round(
                Haversine::metersBetween(
                    (float) $school->latitude,
                    (float) $school->longitude,
                    $pickupStart['latitude'],
                    $pickupStart['longitude'],
                ) / 1000,
                2,
            ),
            'transport_route_id' => null,
            'route_student_ids' => [],
            'start_address' => $schoolAddress !== '' ? $schoolAddress : null,
            'end_address' => $pickupAddress !== '' ? $pickupAddress : null,
            'route_start_latitude' => (float) $school->latitude,
            'route_start_longitude' => (float) $school->longitude,
            'route_end_latitude' => $pickupStart['latitude'],
            'route_end_longitude' => $pickupStart['longitude'],
            'school_latitude' => (float) $school->latitude,
            'school_longitude' => (float) $school->longitude,
        ];
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

    private function locationFromPoints(string $start, string $end): ?string
    {
        if ($start === '' && $end === '') {
            return null;
        }

        if ($start === '') {
            return __('dashboard.trip_location_end_only', ['end' => $end]);
        }

        if ($end === '') {
            return __('dashboard.trip_location_start_only', ['start' => $start]);
        }

        return __('dashboard.trip_location_start_to_end', [
            'start' => $start,
            'end' => $end,
        ]);
    }

    private function returnLocationLabel(string $schoolAddress, string $pickupStartAddress): ?string
    {
        if ($schoolAddress === '' && $pickupStartAddress === '') {
            return null;
        }

        if ($schoolAddress === '') {
            return __('dashboard.trip_location_end_only', ['end' => $pickupStartAddress]);
        }

        if ($pickupStartAddress === '') {
            return __('dashboard.trip_location_start_only', ['start' => $schoolAddress]);
        }

        return __('dashboard.trip_location_school_to_pickup_start', [
            'start' => $schoolAddress,
            'end' => $pickupStartAddress,
        ]);
    }

    private function returnRouteTitle(string $pickupRouteTitle, ?TripType $returnType): string
    {
        $suffix = match ($returnType) {
            TripType::MORNING_RETURN => __('dashboard.trip_return_route_title_morning'),
            TripType::EVENING_RETURN => __('dashboard.trip_return_route_title_evening'),
            default => __('dashboard.trip_return_route_title_generic'),
        };

        $pickupRouteTitle = trim($pickupRouteTitle);

        return $pickupRouteTitle === '' ? $suffix : $pickupRouteTitle.' — '.$suffix;
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
     *     end_address: string|null,
     *     route_start_latitude: float|null,
     *     route_start_longitude: float|null,
     *     school_latitude: float|null,
     *     school_longitude: float|null
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
            'route_start_latitude' => $route->start_latitude !== null ? (float) $route->start_latitude : null,
            'route_start_longitude' => $route->start_longitude !== null ? (float) $route->start_longitude : null,
            'school_latitude' => $school?->latitude !== null ? (float) $school->latitude : null,
            'school_longitude' => $school?->longitude !== null ? (float) $school->longitude : null,
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
