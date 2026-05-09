<?php

namespace App\Services\TransportLines;

use App\Models\Driver;
use App\Models\School;
use App\Models\Student;
use App\Models\TripHistory;
use App\Models\TripRequest;
use App\Models\User;
use Illuminate\Support\Collection;

final class TransportDriverCardBuilder
{
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
     * @param  list<int>  $schoolIds
     * @param  Collection<int, Driver>  $drivers
     * @return array<string, string> keys "{schoolId}|{busNumber}"
     */
    public function latestRouteTitlesBySchoolAndBus(array $schoolIds, Collection $drivers): array
    {
        $numbers = $drivers
            ->map(fn (Driver $d) => $d->bus?->number)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($numbers === [] || $schoolIds === []) {
            return [];
        }

        $rows = TripHistory::query()
            ->whereIn('school_id', $schoolIds)
            ->whereIn('bus_number', $numbers)
            ->whereNotNull('route_title')
            ->orderByDesc('start_time')
            ->get(['school_id', 'bus_number', 'route_title']);

        $out = [];
        foreach ($rows as $row) {
            $bn = (string) $row->bus_number;
            if ($bn === '') {
                continue;
            }
            $key = (int) $row->school_id.'|'.$bn;
            if (! array_key_exists($key, $out)) {
                $out[$key] = (string) $row->route_title;
            }
        }

        return $out;
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
     * Priority: optional request GPS → student's stored lat/lng → guardian home location.
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
     * @param  array<string, string>  $routeBySchoolAndBus
     * @return array<string, mixed>
     */
    public function buildCard(
        Driver $driver,
        Collection $reservedByDriver,
        array $routeBySchoolAndBus,
        ?float $distanceKm,
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
        $routeKey = $plate !== null ? (int) $driver->school_id.'|'.(string) $plate : '';
        $routeDescription = ($routeKey !== '' && isset($routeBySchoolAndBus[$routeKey]))
            ? $routeBySchoolAndBus[$routeKey]
            : null;

        return [
            'schoolId' => (string) $driver->school_id,
            'driverId' => (string) $driver->id,
            'driverName' => $this->driverDisplayName($driver),
            'profileImageUrl' => $this->normalizePublicAssetUrl($user?->image),
            'routeDescription' => $routeDescription,
            'ratingAvg' => $ratingAvg,
            'ratingCount' => $ratingCount,
            'vehicleType' => $bus?->type,
            'vehicleModelYear' => null,
            'totalSeats' => $capacity,
            'availableSeats' => $availableSeats,
            'plateNumber' => $plate,
            'acStatus' => null,
            'distanceKm' => $distanceKm,
            'monthlyPrice' => null,
            'currency' => 'IQD',
        ];
    }

    public function driverDisplayName(Driver $driver): string
    {
        $driver->loadMissing('user');
        $fromUser = trim((string) ($driver->user?->name ?? ''));
        if ($fromUser !== '') {
            return $fromUser;
        }

        $parts = array_filter([
            $driver->first_name,
            $driver->father_name,
            $driver->grandfather_name,
            $driver->last_name,
        ], fn (?string $p): bool => is_string($p) && $p !== '');

        return implode(' ', $parts);
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
