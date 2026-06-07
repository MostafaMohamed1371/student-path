<?php

namespace App\Services\TransportLines;

use App\Models\Bus;
use App\Models\Driver;
use App\Models\School;
use App\Models\Student;
use App\Models\TransportRoute;
use App\Models\TripHistory;
use App\Models\TripRequest;
use App\Models\User;
use App\Services\Drivers\DriverServiceAreaStudentMatcher;
use App\Services\Routes\RouteAssignmentPlanner;
use Illuminate\Support\Collection;

final class TransportDriverCardBuilder
{
    public function __construct(
        private readonly RouteAssignmentPlanner $routeAssignmentPlanner,
        private readonly DriverServiceAreaStudentMatcher $serviceAreaStudentMatcher,
    ) {}
    /**
     * @param  Collection<int, Driver>  $drivers
     * @return Collection<int|string, int>
     */
    public function reservedCountsByDriverId(Collection $drivers): Collection
    {
        if ($drivers->isEmpty()) {
            return collect();
        }

        $ids = $drivers->pluck('id')->filter()->values()->all();
        if ($ids === []) {
            return collect();
        }

        return TripRequest::query()
            ->selectRaw('driver_id, COUNT(*) as c')
            ->whereIn('driver_id', $ids)
            ->whereIn('status', ['pending', 'accepted'])
            ->groupBy('driver_id')
            ->pluck('c', 'driver_id');
    }

    /**
     * Latest trip route metadata per driver (route title + optional map start).
     *
     * @param  list<int>  $schoolIds
     * @param  Collection<int, Driver>  $drivers
     * @return array<int, array{
     *     route_title: string,
     *     start_address: string|null,
     *     start_latitude: float|null,
     *     start_longitude: float|null
     * }>
     */
    public function latestTripRouteMetaForDrivers(array $schoolIds, Collection $drivers): array
    {
        if ($drivers->isEmpty()) {
            return [];
        }

        $driverIds = $drivers->pluck('id')->filter()->map(fn ($id): int => (int) $id)->values()->all();
        $out = [];

        if ($driverIds !== []) {
            $rows = TripHistory::query()
                ->whereIn('driver_id', $driverIds)
                ->whereNotNull('route_title')
                ->where('route_title', '!=', '')
                ->orderByDesc('start_time')
                ->get(['driver_id', 'route_title', 'start_address', 'start_latitude', 'start_longitude']);

            foreach ($rows as $row) {
                $driverId = (int) $row->driver_id;
                if ($driverId <= 0 || array_key_exists($driverId, $out)) {
                    continue;
                }
                $out[$driverId] = $this->tripRouteMetaFromRow($row);
            }
        }

        $numbers = $drivers
            ->map(fn (Driver $d) => $d->bus?->number)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($numbers === [] || $schoolIds === []) {
            return $out;
        }

        $rows = TripHistory::query()
            ->whereIn('school_id', $schoolIds)
            ->whereIn('bus_number', $numbers)
            ->whereNotNull('route_title')
            ->where('route_title', '!=', '')
            ->orderByDesc('start_time')
            ->get(['driver_id', 'school_id', 'bus_number', 'route_title', 'start_address', 'start_latitude', 'start_longitude']);

        $bySchoolBus = [];
        foreach ($rows as $row) {
            $bn = (string) $row->bus_number;
            if ($bn === '') {
                continue;
            }
            $key = (int) $row->school_id.'|'.$bn;
            if (! array_key_exists($key, $bySchoolBus)) {
                $bySchoolBus[$key] = $this->tripRouteMetaFromRow($row);
            }
        }

        foreach ($drivers as $driver) {
            $driverId = (int) $driver->id;
            if (array_key_exists($driverId, $out)) {
                continue;
            }

            $plate = $driver->bus?->number;
            if ($plate === null || $plate === '') {
                continue;
            }

            $key = (int) $driver->school_id.'|'.(string) $plate;
            if (isset($bySchoolBus[$key])) {
                $out[$driverId] = $bySchoolBus[$key];
            }
        }

        return $out;
    }

    /**
     * Active transport routes for drivers (one route per driver per trip type).
     *
     * @param  Collection<int, Driver>  $drivers
     * @return Collection<int, TransportRoute> keyed by driver_id
     */
    public function activeTransportRoutesByDriverId(Collection $drivers, ?string $tripType = null): Collection
    {
        if ($drivers->isEmpty()) {
            return collect();
        }

        $driverIds = $drivers->pluck('id')->filter()->values()->all();
        if ($driverIds === []) {
            return collect();
        }

        $query = TransportRoute::query()
            ->with('school')
            ->whereIn('driver_id', $driverIds)
            ->where('status', 'active')
            ->whereNotNull('start_latitude')
            ->whereNotNull('start_longitude');

        $tripType = trim((string) ($tripType ?? ''));
        if ($tripType !== '') {
            $query->where('trip_type', $tripType);
        }

        return $query->get()->keyBy('driver_id');
    }

    public function resolveViewerLatLng(?float $queryLat, ?float $queryLng, ?User $user): ?array
    {
        if ($queryLat !== null && $queryLng !== null) {
            return [$queryLat, $queryLng];
        }

        $user?->loadMissing('homeLocation');
        $home = $user?->homeLocation;
        if ($home && $home->latitude !== null && $home->longitude !== null) {
            return [(float) $home->latitude, (float) $home->longitude];
        }

        return null;
    }

    public function distanceKmToSchool(?array $viewerLatLng, ?School $school): ?float
    {
        if ($viewerLatLng === null || ! $school instanceof School) {
            return null;
        }

        [$lat, $lng] = $viewerLatLng;

        if ($school->latitude === null || $school->longitude === null) {
            return null;
        }

        return $this->haversineKm($lat, $lng, (float) $school->latitude, (float) $school->longitude);
    }

    /**
     * Straight-line km from a pickup point to the school location.
     */
    public function distanceKmPickupToSchool(float $pickupLat, float $pickupLng, School $school): ?float
    {
        if ($school->latitude === null || $school->longitude === null) {
            return null;
        }

        return $this->haversineKm($pickupLat, $pickupLng, (float) $school->latitude, (float) $school->longitude);
    }

    /**
     * Distance shown on driver cards: pickup → school.
     *
     * Priority: optional request GPS → student's stored lat/lng (explicit or per-school default from
     * {@see \App\Support\ParentContext::representativeStudentsWithLocationBySchool}) → guardian home location.
     * Student and school coordinates are optional; when missing, falls back or returns null.
     */
    public function resolveDistanceKmToSchool(
        ?float $overrideLat,
        ?float $overrideLng,
        ?Student $student,
        ?User $user,
        ?School $school,
    ): ?float {
        if (! $school instanceof School) {
            return null;
        }

        if ($overrideLat !== null && $overrideLng !== null) {
            return $this->distanceKmPickupToSchool($overrideLat, $overrideLng, $school);
        }

        $student?->loadMissing('school');
        if ($student !== null
            && $student->latitude !== null
            && $student->longitude !== null) {
            return $this->distanceKmPickupToSchool(
                (float) $student->latitude,
                (float) $student->longitude,
                $school
            );
        }

        $viewerLatLng = $this->resolveViewerLatLng(null, null, $user);

        return $this->distanceKmToSchool($viewerLatLng, $school);
    }

    /**
     * @param  Collection<int|string, int>  $reservedByDriver
     * @param  array<int, array{
     *     route_title: string,
     *     start_address: string|null,
     *     start_latitude: float|null,
     *     start_longitude: float|null
     * }>  $tripRouteMetaByDriver
     * @return array<string, mixed>
     */
    public function buildCard(
        Driver $driver,
        Collection $reservedByDriver,
        array $tripRouteMetaByDriver,
        ?float $distanceKm,
        ?TransportRoute $transportRoute = null,
        ?Student $studentForRouteMatch = null,
    ): array {
        $driver->loadMissing(['user', 'bus']);
        $user = $driver->user;
        $bus = $driver->bus;

        $ratingAvg = $user !== null ? round((float) $user->rate, 1) : null;
        $ratingCount = $user !== null ? (int) $user->votes : 0;

        $capacity = $bus !== null ? (int) $bus->capacity : null;
        $reserved = (int) ($reservedByDriver->get($driver->id) ?? 0);
        $availableSeats = $capacity !== null ? max(0, $capacity - $reserved) : null;
        $plate = $bus?->number;

        $latestTripRoute = $tripRouteMetaByDriver[(int) $driver->id] ?? null;
        $routeDescription = $this->resolveRouteDescription($driver, $bus, $latestTripRoute, $transportRoute);

        $matchesStudentRoute = null;
        if ($studentForRouteMatch !== null) {
            $school = $driver->relationLoaded('school')
                ? $driver->school
                : School::query()->find($driver->school_id);

            if ($school instanceof School
                && $this->serviceAreaStudentMatcher->studentMatchesDriverServiceAreas(
                    $studentForRouteMatch,
                    $driver,
                )) {
                $matchesStudentRoute = true;
            } elseif ($transportRoute !== null) {
                $matchesStudentRoute = $this->routeAssignmentPlanner->studentMatchesRouteCorridor(
                    $studentForRouteMatch,
                    $transportRoute,
                );
            } else {
                $matchesStudentRoute = false;
            }
        }

        return [
            'schoolId' => (string) $driver->school_id,
            'driverId' => (string) $driver->id,
            'driverName' => $this->driverDisplayName($driver),
            'profileImageUrl' => $this->normalizePublicAssetUrl($user?->image),
            'routeDescription' => $routeDescription,
            'route' => $transportRoute !== null
                ? $this->formatTransportRoute($transportRoute, $latestTripRoute)
                : null,
            'matchesStudentRoute' => $matchesStudentRoute,
            'ratingAvg' => $ratingAvg,
            'ratingCount' => $ratingCount,
            'vehicleType' => $bus?->type,
            'vehicleModelYear' => $bus?->vehicle_model_year !== null
                ? (int) $bus->vehicle_model_year
                : null,
            'totalSeats' => $capacity,
            'availableSeats' => $availableSeats,
            'plateNumber' => $plate,
            'acStatus' => $bus?->ac_status,
            'distanceKm' => $distanceKm,
            'monthlyPrice' => $driver->monthly_subscription_price !== null
                ? (int) $driver->monthly_subscription_price
                : null,
            'currency' => 'IQD',
        ];
    }

    /**
     * @param  array{
     *     route_title: string,
     *     start_address: string|null,
     *     start_latitude: float|null,
     *     start_longitude: float|null
     * }|null  $latestTripRoute
     * @return array<string, mixed>
     */
    public function formatTransportRoute(TransportRoute $route, ?array $latestTripRoute = null): array
    {
        $route->loadMissing('school');

        $name = trim((string) ($latestTripRoute['route_title'] ?? ''));
        if ($name === '') {
            $name = trim((string) ($route->name ?? ''));
        }

        $startAddress = trim((string) ($latestTripRoute['start_address'] ?? ''));
        if ($startAddress === '') {
            $startAddress = trim((string) ($route->start_address ?? ''));
        }

        $startLatitude = $latestTripRoute['start_latitude'] ?? null;
        if ($startLatitude === null && $route->start_latitude !== null) {
            $startLatitude = (float) $route->start_latitude;
        }

        $startLongitude = $latestTripRoute['start_longitude'] ?? null;
        if ($startLongitude === null && $route->start_longitude !== null) {
            $startLongitude = (float) $route->start_longitude;
        }

        return [
            'routeId' => (string) $route->id,
            'name' => $name !== '' ? $name : null,
            'tripType' => $route->trip_type,
            'startAddress' => $startAddress !== '' ? $startAddress : null,
            'startLatitude' => $startLatitude,
            'startLongitude' => $startLongitude,
            'schoolAddress' => $route->school?->address,
            'schoolLatitude' => $route->school?->latitude !== null ? (float) $route->school->latitude : null,
            'schoolLongitude' => $route->school?->longitude !== null ? (float) $route->school->longitude : null,
        ];
    }

    /**
     * @param  array{
     *     route_title: string,
     *     start_address: string|null,
     *     start_latitude: float|null,
     *     start_longitude: float|null
     * }|null  $latestTripRoute
     */
    private function resolveRouteDescription(
        Driver $driver,
        ?Bus $bus,
        ?array $latestTripRoute,
        ?TransportRoute $transportRoute = null,
    ): ?string {
        $tripTitle = trim((string) ($latestTripRoute['route_title'] ?? ''));
        if ($tripTitle !== '') {
            return $tripTitle;
        }

        if ($transportRoute !== null) {
            $name = trim((string) $transportRoute->name);
            $start = trim((string) ($transportRoute->start_address ?? ''));
            if ($name !== '' && $start !== '') {
                return $name.' — '.$start;
            }
            if ($name !== '') {
                return $name;
            }
            if ($start !== '') {
                return $start;
            }
        }

        $driverText = $driver->route_description;
        if (is_string($driverText) && trim($driverText) !== '') {
            return trim($driverText);
        }

        if ($bus !== null && is_string($bus->name) && trim($bus->name) !== '') {
            return trim($bus->name);
        }

        return null;
    }

    /**
     * @return array{
     *     route_title: string,
     *     start_address: string|null,
     *     start_latitude: float|null,
     *     start_longitude: float|null
     * }
     */
    private function tripRouteMetaFromRow(TripHistory $row): array
    {
        return [
            'route_title' => trim((string) $row->route_title),
            'start_address' => is_string($row->start_address) && trim($row->start_address) !== ''
                ? trim($row->start_address)
                : null,
            'start_latitude' => $row->start_latitude !== null ? (float) $row->start_latitude : null,
            'start_longitude' => $row->start_longitude !== null ? (float) $row->start_longitude : null,
        ];
    }

    public function driverDisplayName(Driver $driver): string
    {
        $parts = array_filter([
            $driver->first_name,
            $driver->father_name,
            $driver->grandfather_name,
            $driver->last_name,
        ], fn (?string $p): bool => is_string($p) && trim($p) !== '');

        $fromDriver = trim(implode(' ', $parts));
        if ($fromDriver !== '') {
            return $fromDriver;
        }

        $driver->loadMissing('user');

        return trim((string) ($driver->user?->name ?? ''));
    }

    public function normalizePublicAssetUrl(?string $path): ?string
    {
        if (! is_string($path) || $path === '') {
            return null;
        }

        $normalized = ltrim($path, '/');
        $normalized = (string) preg_replace('#^(?:student-path/)?storage/app/public/#', '', $normalized);
        $normalized = (string) preg_replace('#^public/storage/#', '', $normalized);

        return '/student-path/storage/app/public/'.$normalized;
    }

    public function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthKm = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return round($earthKm * 2 * atan2(sqrt($a), sqrt(1 - $a)), 2);
    }
}
