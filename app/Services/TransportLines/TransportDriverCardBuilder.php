<?php

namespace App\Services\TransportLines;

use App\Enums\TripType;
use App\Models\Bus;
use App\Models\Driver;
use App\Models\School;
use App\Models\Student;
use App\Models\TransportRoute;
use App\Models\TripHistory;
use App\Models\TripRequest;
use App\Models\User;
use App\Services\Drivers\DriverServiceAreaStudentMatcher;
use App\Services\Drivers\DriverServiceAreaTripFormatter;
use App\Services\Routes\RouteAssignmentPlanner;
use Illuminate\Support\Collection;

final class TransportDriverCardBuilder
{
    public function __construct(
        private readonly RouteAssignmentPlanner $routeAssignmentPlanner,
        private readonly DriverServiceAreaStudentMatcher $serviceAreaStudentMatcher,
        private readonly DriverServiceAreaTripFormatter $serviceAreaTripFormatter,
    ) {}

    /**
     * @param  Collection<int, Driver>  $drivers
     * @return array<int, list<array<string, mixed>>>
     */
    public function addressInformationForDrivers(Collection $drivers): array
    {
        return $this->serviceAreaTripFormatter->addressInformationByDriverIds(
            $drivers->pluck('id')->map(fn ($id): int => (int) $id)->all(),
        );
    }

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
     * @param  list<string>|null  $tripTypes  When $tripType is empty, limit to these types (pickup preferred on card).
     * @return array<int, array{
     *     trip_id: int,
     *     school_id: int,
     *     trip_type: string|null,
     *     route_title: string,
     *     start_address: string|null,
     *     start_latitude: float|null,
     *     start_longitude: float|null
     * }>
     */
    public function latestTripRouteMetaForDrivers(
        array $schoolIds,
        Collection $drivers,
        ?string $tripType = null,
        ?array $tripTypes = null,
    ): array {
        if ($drivers->isEmpty()) {
            return [];
        }

        $tripType = trim((string) ($tripType ?? ''));

        $driverIds = $drivers->pluck('id')->filter()->map(fn ($id): int => (int) $id)->values()->all();
        $out = [];

        if ($driverIds !== []) {
            $query = TripHistory::query()
                ->whereIn('driver_id', $driverIds)
                ->whereNotNull('route_title')
                ->where('route_title', '!=', '')
                ->whereNotIn('status', ['CANCELLED', 'COMPLETED', 'DONE'])
                ->where('start_time', '>=', now()->startOfDay())
                ->orderBy('start_time')
                ->orderBy('id');

            if ($tripType !== '') {
                $query->where('trip_type', $tripType);
            } elseif ($tripTypes !== null && $tripTypes !== []) {
                $query->whereIn('trip_type', $tripTypes);
            }

            $rows = $query->get([
                'id',
                'driver_id',
                'school_id',
                'trip_type',
                'route_title',
                'start_address',
                'start_latitude',
                'start_longitude',
                'start_time',
            ]);

            foreach ($rows->groupBy('driver_id') as $driverId => $driverRows) {
                $driverId = (int) $driverId;
                if ($driverId <= 0 || array_key_exists($driverId, $out)) {
                    continue;
                }
                $out[$driverId] = $this->tripRouteMetaFromRow($this->preferredTripRouteRow($driverRows));
            }

            $missingDriverIds = array_values(array_diff($driverIds, array_keys($out)));
            if ($missingDriverIds !== []) {
                $pastQuery = TripHistory::query()
                    ->whereIn('driver_id', $missingDriverIds)
                    ->whereNotNull('route_title')
                    ->where('route_title', '!=', '')
                    ->orderByDesc('start_time')
                    ->orderByDesc('id');

                if ($tripType !== '') {
                    $pastQuery->where('trip_type', $tripType);
                } elseif ($tripTypes !== null && $tripTypes !== []) {
                    $pastQuery->whereIn('trip_type', $tripTypes);
                }

                foreach ($pastQuery->get([
                    'id',
                    'driver_id',
                    'school_id',
                    'trip_type',
                    'route_title',
                    'start_address',
                    'start_latitude',
                    'start_longitude',
                    'start_time',
                ])->groupBy('driver_id') as $driverId => $driverRows) {
                    $driverId = (int) $driverId;
                    if ($driverId <= 0 || array_key_exists($driverId, $out)) {
                        continue;
                    }
                    $out[$driverId] = $this->tripRouteMetaFromRow($this->preferredTripRouteRow($driverRows));
                }
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

        $fallbackQuery = TripHistory::query()
            ->whereIn('school_id', $schoolIds)
            ->whereIn('bus_number', $numbers)
            ->whereNotNull('route_title')
            ->where('route_title', '!=', '')
            ->whereNotIn('status', ['CANCELLED', 'COMPLETED', 'DONE'])
            ->where('start_time', '>=', now()->startOfDay())
            ->orderBy('start_time')
            ->orderBy('id');

        if ($tripType !== '') {
            $fallbackQuery->where('trip_type', $tripType);
        } elseif ($tripTypes !== null && $tripTypes !== []) {
            $fallbackQuery->whereIn('trip_type', $tripTypes);
        }

        $rows = $fallbackQuery->get([
            'id',
            'driver_id',
            'school_id',
            'bus_number',
            'trip_type',
            'route_title',
            'start_address',
            'start_latitude',
            'start_longitude',
            'start_time',
        ]);

        $bySchoolBus = [];
        foreach ($rows->groupBy(fn (TripHistory $row): string => (int) $row->school_id.'|'.(string) $row->bus_number) as $key => $busRows) {
            if ($key === '0|' || str_ends_with($key, '|')) {
                continue;
            }
            $bySchoolBus[$key] = $this->tripRouteMetaFromRow($this->preferredTripRouteRow($busRows));
        }

        $pastFallbackQuery = TripHistory::query()
            ->whereIn('school_id', $schoolIds)
            ->whereIn('bus_number', $numbers)
            ->whereNotNull('route_title')
            ->where('route_title', '!=', '')
            ->orderByDesc('start_time')
            ->orderByDesc('id');

        if ($tripType !== '') {
            $pastFallbackQuery->where('trip_type', $tripType);
        } elseif ($tripTypes !== null && $tripTypes !== []) {
            $pastFallbackQuery->whereIn('trip_type', $tripTypes);
        }

        foreach ($pastFallbackQuery->get([
            'id',
            'driver_id',
            'school_id',
            'bus_number',
            'trip_type',
            'route_title',
            'start_address',
            'start_latitude',
            'start_longitude',
            'start_time',
        ])->groupBy(fn (TripHistory $row): string => (int) $row->school_id.'|'.(string) $row->bus_number) as $key => $busRows) {
            if ($key === '0|' || str_ends_with($key, '|') || array_key_exists($key, $bySchoolBus)) {
                continue;
            }
            $bySchoolBus[$key] = $this->tripRouteMetaFromRow($this->preferredTripRouteRow($busRows));
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
    public function activeTransportRoutesByDriverId(Collection $drivers, ?string $tripType = null, ?array $tripTypes = null): Collection
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
        } elseif ($tripTypes !== null && $tripTypes !== []) {
            $query->whereIn('trip_type', $tripTypes);
        }

        return $query->get()
            ->groupBy('driver_id')
            ->map(fn (Collection $routes): TransportRoute => $this->preferredTransportRoute($routes))
            ->filter();
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
     * @param  array<int, list<array<string, mixed>>>|null  $addressInformationByDriver
     * @return array<string, mixed>
     */
    public function buildCard(
        Driver $driver,
        Collection $reservedByDriver,
        array $tripRouteMetaByDriver,
        ?float $distanceKm,
        ?TransportRoute $transportRoute = null,
        ?Student $studentForRouteMatch = null,
        ?array $addressInformationByDriver = null,
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
        $routeDescription = $this->resolveRouteDescription($driver, $bus, $latestTripRoute);

        $school = $driver->relationLoaded('school')
            ? $driver->school
            : School::query()->find($driver->school_id);

        $matchesStudentRoute = null;
        if ($studentForRouteMatch !== null) {
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
            } elseif ($latestTripRoute !== null) {
                $matchesStudentRoute = true;
            } else {
                $matchesStudentRoute = false;
            }
        }

        $driverId = (int) $driver->id;
        $addressInformation = $addressInformationByDriver !== null
            ? ($addressInformationByDriver[$driverId] ?? $this->serviceAreaTripFormatter->serviceAreasForDriver($driverId))
            : $this->serviceAreaTripFormatter->serviceAreasForDriver($driverId);

        return [
            'schoolId' => (string) $driver->school_id,
            'driverId' => (string) $driver->id,
            'driverName' => $this->driverDisplayName($driver),
            'profileImageUrl' => $this->normalizePublicAssetUrl($user?->image),
            'routeDescription' => $routeDescription,
            'route' => $this->formatRouteForDriverCard($latestTripRoute, $school instanceof School ? $school : null),
            'hasScheduledTrip' => $latestTripRoute !== null,
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
            'address_information' => $addressInformation,
        ];
    }

    /**
     * Route payload for transport-lines cards: latest assigned trip only.
     *
     * @param  array{
     *     trip_id: int,
     *     school_id: int,
     *     trip_type: string|null,
     *     route_title: string,
     *     start_address: string|null,
     *     start_latitude: float|null,
     *     start_longitude: float|null
     * }|null  $latestTripRoute
     * @return array<string, mixed>|null
     */
    public function formatRouteForDriverCard(?array $latestTripRoute, ?School $school): ?array
    {
        if ($latestTripRoute === null) {
            return null;
        }

        if (! $school instanceof School && ($latestTripRoute['school_id'] ?? 0) > 0) {
            $school = School::query()->find((int) $latestTripRoute['school_id']);
        }

        $name = trim((string) ($latestTripRoute['route_title'] ?? ''));

        return [
            'routeId' => (string) ($latestTripRoute['trip_id'] ?? 0),
            'tripId' => (string) ($latestTripRoute['trip_id'] ?? 0),
            'name' => $name !== '' ? $name : null,
            'tripType' => $latestTripRoute['trip_type'] ?? null,
            'startAddress' => $latestTripRoute['start_address'] ?? null,
            'startLatitude' => $latestTripRoute['start_latitude'] ?? null,
            'startLongitude' => $latestTripRoute['start_longitude'] ?? null,
            'schoolAddress' => $school?->address,
            'schoolLatitude' => $school?->latitude !== null ? (float) $school->latitude : null,
            'schoolLongitude' => $school?->longitude !== null ? (float) $school->longitude : null,
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
    ): ?string {
        $tripTitle = trim((string) ($latestTripRoute['route_title'] ?? ''));
        if ($tripTitle !== '') {
            return $tripTitle;
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
     *     trip_id: int,
     *     school_id: int,
     *     trip_type: string|null,
     *     route_title: string,
     *     start_address: string|null,
     *     start_latitude: float|null,
     *     start_longitude: float|null
     * }
     */
    private function tripRouteMetaFromRow(TripHistory $row): array
    {
        return [
            'trip_id' => (int) $row->id,
            'school_id' => (int) $row->school_id,
            'trip_type' => is_string($row->trip_type) && trim($row->trip_type) !== ''
                ? trim($row->trip_type)
                : null,
            'route_title' => trim((string) $row->route_title),
            'start_address' => is_string($row->start_address) && trim($row->start_address) !== ''
                ? trim($row->start_address)
                : null,
            'start_latitude' => $row->start_latitude !== null ? (float) $row->start_latitude : null,
            'start_longitude' => $row->start_longitude !== null ? (float) $row->start_longitude : null,
        ];
    }

    /**
     * @param  Collection<int, TripHistory>  $rows
     */
    private function preferredTripRouteRow(Collection $rows): TripHistory
    {
        return $rows->sortBy([
            fn (TripHistory $row): int => TripType::isReturn((string) ($row->trip_type ?? '')) ? 1 : 0,
            fn (TripHistory $row): string => (string) $row->start_time,
            fn (TripHistory $row): int => (int) $row->id,
        ])->first();
    }

    /**
     * @param  Collection<int, TransportRoute>  $routes
     */
    private function preferredTransportRoute(Collection $routes): TransportRoute
    {
        return $routes->sortBy([
            fn (TransportRoute $route): int => TripType::isReturn((string) ($route->trip_type ?? '')) ? 1 : 0,
            fn (TransportRoute $route): int => (int) $route->id,
        ])->first();
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

    /**
     * Drivers with a scheduled trip row (not only a transport route) for the given school + trip type(s).
     *
     * @param  list<int>  $schoolIds
     * @param  list<string>|null  $tripTypes
     * @return list<int>
     */
    public function scheduledTripDriverIds(
        array $schoolIds,
        ?string $tripType = null,
        ?array $tripTypes = null,
    ): array {
        if ($schoolIds === []) {
            return [];
        }

        $query = TripHistory::query()
            ->whereIn('school_id', $schoolIds)
            ->whereNotNull('driver_id')
            ->whereNotNull('route_title')
            ->where('route_title', '!=', '')
            ->whereNotIn('status', ['CANCELLED', 'COMPLETED', 'DONE'])
            ->where('start_time', '>=', now()->startOfDay());

        $tripType = trim((string) ($tripType ?? ''));
        if ($tripType !== '') {
            $query->where('trip_type', $tripType);
        } elseif ($tripTypes !== null && $tripTypes !== []) {
            $query->whereIn('trip_type', $tripTypes);
        }

        return $query
            ->pluck('driver_id')
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $schoolIds
     */
    public function driverHasScheduledTripOfType(int $driverId, array $schoolIds, string $tripType): bool
    {
        if ($driverId <= 0 || $tripType === '') {
            return false;
        }

        return in_array($driverId, $this->scheduledTripDriverIds($schoolIds, $tripType), true);
    }
}
