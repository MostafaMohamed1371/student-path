<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Concerns\AppliesApiSchoolScoping;
use App\Http\Controllers\Api\V1\Concerns\FormatsParentApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\Guardian;
use App\Models\School;
use App\Models\TripHistory;
use App\Models\TripRequest;
use App\Support\ParentContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class TransportLinesDriverController extends Controller
{
    use AppliesApiSchoolScoping;
    use FormatsParentApiResponse;

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'school_id' => ['nullable', 'integer', 'exists:schools,id'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $resolved = $this->resolveTargetSchoolIds($request);
        if ($resolved instanceof JsonResponse) {
            return $resolved;
        }

        /** @var list<int> $schoolIds */
        $schoolIds = $resolved;

        foreach ($schoolIds as $sid) {
            if ($resp = $this->ensureApiTargetsOwnSchoolOrAdmin($request->user(), $sid)) {
                return $resp;
            }
        }

        $schools = School::query()->whereIn('id', $schoolIds)->get()->keyBy('id');

        $drivers = Driver::query()
            ->whereIn('school_id', $schoolIds)
            ->where('status', 'active')
            ->with(['user', 'bus'])
            ->orderBy('school_id')
            ->orderBy('id')
            ->get();

        $viewerLatLng = $this->resolveViewerLatLng($request);

        $reservedByDriver = TripRequest::query()
            ->selectRaw('driver_id, COUNT(*) as c')
            ->whereIn('driver_id', $drivers->modelKeys())
            ->whereIn('status', ['pending', 'accepted'])
            ->groupBy('driver_id')
            ->pluck('c', 'driver_id');

        $routeBySchoolAndBus = $this->latestRouteTitlesBySchoolAndBus($schoolIds, $drivers);

        $cards = $drivers->map(function (Driver $driver) use ($reservedByDriver, $routeBySchoolAndBus, $schools, $viewerLatLng): array {
            $school = $schools->get($driver->school_id);
            $distanceKm = ($school instanceof School)
                ? $this->distanceKmToSchool($viewerLatLng, $school)
                : null;

            return $this->driverCardPayload($driver, $reservedByDriver, $routeBySchoolAndBus, $distanceKm);
        })->values()->all();

        return $this->parentSuccess([
            'schoolIds' => array_map(static fn (int $id): string => (string) $id, $schoolIds),
            'drivers' => $cards,
        ]);
    }

    /**
     * @return JsonResponse|list<int>
     */
    private function resolveTargetSchoolIds(Request $request): JsonResponse|array
    {
        $user = $request->user();

        if ($request->filled('school_id')) {
            return [(int) $request->query('school_id')];
        }

        if ($this->isApiAdmin($user)) {
            return $this->parentError('school_id is required', null, 422);
        }

        $studentSchoolIds = ParentContext::studentsFor($user)
            ->pluck('school_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->filter(fn (int $id): bool => $id > 0)
            ->values()
            ->all();

        if ($studentSchoolIds !== []) {
            return array_values(array_unique($studentSchoolIds));
        }

        $guardian = ParentContext::guardian($user);
        if ($guardian instanceof Guardian && $guardian->school_id) {
            return [(int) $guardian->school_id];
        }

        return $this->parentError(
            'Link your account to a guardian with a school, add students, or pass school_id.',
            null,
            403
        );
    }

    /**
     * @return array{0: ?float, 1: ?float}|null
     */
    private function resolveViewerLatLng(Request $request): ?array
    {
        $lat = $request->filled('latitude') ? (float) $request->query('latitude') : null;
        $lng = $request->filled('longitude') ? (float) $request->query('longitude') : null;

        if ($lat === null || $lng === null) {
            $request->user()->loadMissing('homeLocation');
            $home = $request->user()->homeLocation;
            if ($home && $home->latitude !== null && $home->longitude !== null) {
                $lat = (float) $home->latitude;
                $lng = (float) $home->longitude;
            }
        }

        if ($lat === null || $lng === null) {
            return null;
        }

        return [$lat, $lng];
    }

    /**
     * @param  array{0: ?float, 1: ?float}|null  $viewerLatLng
     */
    private function distanceKmToSchool(?array $viewerLatLng, School $school): ?float
    {
        if ($viewerLatLng === null) {
            return null;
        }

        [$lat, $lng] = $viewerLatLng;

        if ($school->latitude === null || $school->longitude === null) {
            return null;
        }

        return $this->haversineKm($lat, $lng, (float) $school->latitude, (float) $school->longitude);
    }

    /**
     * @param  list<int>  $schoolIds
     * @param  Collection<int, Driver>  $drivers
     * @return array<string, string> keys "{schoolId}|{busNumber}"
     */
    private function latestRouteTitlesBySchoolAndBus(array $schoolIds, Collection $drivers): array
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

    /**
     * @param  Collection<int|string, mixed>  $reservedByDriver
     * @param  array<string, string>  $routeBySchoolAndBus
     * @return array<string, mixed>
     */
    private function driverCardPayload(
        Driver $driver,
        Collection $reservedByDriver,
        array $routeBySchoolAndBus,
        ?float $distanceKm,
    ): array {
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

    private function driverDisplayName(Driver $driver): string
    {
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

    private function normalizePublicAssetUrl(?string $path): ?string
    {
        if (! is_string($path) || $path === '') {
            return null;
        }

        $normalized = ltrim($path, '/');
        $normalized = (string) preg_replace('#^(?:student-path/)?storage/app/public/#', '', $normalized);
        $normalized = (string) preg_replace('#^public/storage/#', '', $normalized);

        return '/student-path/storage/app/public/'.$normalized;
    }

    private function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthKm = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return round($earthKm * 2 * atan2(sqrt($a), sqrt(1 - $a)), 2);
    }
}
