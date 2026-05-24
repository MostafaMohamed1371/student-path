<?php

namespace App\Services\Trips;

use App\Contracts\Trips\TripLocationRepository;
use App\Events\DriverLocationUpdated;
use App\Models\Bus;
use App\Models\Driver;
use App\Models\School;
use App\Models\Student;
use App\Models\TripHistory;
use App\Models\User;
use App\Services\Push\TripTopicNamer;
use App\Services\Push\TripTrackingAuthorization;
use App\Support\Geo\Haversine;
use App\Support\ParentContext;
use App\Support\Trips\TripPublicId;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Throwable;

final class TripLocationTrackingService
{
    public function __construct(
        private readonly TripLocationRepository $locations,
        private readonly TripTrackingAuthorization $authorization,
        private readonly TripTopicNamer $topicNamer,
    ) {}

    /**
     * @param  array{
     *   latitude: float,
     *   longitude: float,
     *   heading?: float|null,
     *   speed_kmh?: float|null,
     *   accuracy_m?: float|null,
     *   recorded_at?: string|null
     * }  $coords
     *
     * @return array<string, mixed>
     */
    public function updateDriverLocation(Driver $driver, TripHistory $trip, array $coords): array
    {
        $this->assertDriverOwnsActiveTrip($driver, $trip);

        $tripId = (int) $trip->id;
        $driverUserId = (int) ($driver->user_id ?? 0);
        $rateKey = 'trip-location|'.$tripId.'|'.$driver->id;

        if (RateLimiter::tooManyAttempts($rateKey, $this->maxUpdatesPerMinute())) {
            throw ValidationException::withMessages([
                'location' => ['Too many location updates. Please slow down.'],
            ]);
        }

        RateLimiter::hit($rateKey, 60);

        $recordedAt = isset($coords['recorded_at']) && is_string($coords['recorded_at']) && $coords['recorded_at'] !== ''
            ? Carbon::parse($coords['recorded_at'])->toIso8601String()
            : now()->toIso8601String();

        $location = [
            'latitude' => round((float) $coords['latitude'], 7),
            'longitude' => round((float) $coords['longitude'], 7),
            'heading' => isset($coords['heading']) ? round((float) $coords['heading'], 2) : null,
            'speed_kmh' => isset($coords['speed_kmh']) ? round((float) $coords['speed_kmh'], 2) : null,
            'accuracy_m' => isset($coords['accuracy_m']) ? round((float) $coords['accuracy_m'], 2) : null,
            'recorded_at' => $recordedAt,
        ];

        $tracking = [
            'active' => true,
            'driver_id' => (int) $driver->id,
            'driver_user_id' => $driverUserId > 0 ? $driverUserId : null,
            'trip_history_id' => $tripId,
            'updated_at' => now()->toIso8601String(),
            'location' => $location,
        ];

        $this->locations->write($tripId, $tracking);
        $this->broadcastLocationUpdate($tripId, $location, true);

        return [
            'trip_id' => TripPublicId::forTrip($trip),
            'trip_history_id' => $tripId,
            'firebase_path' => $this->locations->trackingPath($tripId),
            'location' => $location,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function trackingPayloadForUser(User $user, TripHistory $trip, ?int $studentId = null): array
    {
        if (! $this->authorization->canSubscribe($user, $trip)) {
            throw ValidationException::withMessages([
                'trip' => ['You are not allowed to track this trip.'],
            ]);
        }

        $trip->loadMissing(['driver.bus', 'school']);
        $tracking = $this->locations->read((int) $trip->id);
        $location = is_array($tracking['location'] ?? null) ? $tracking['location'] : null;
        $active = (bool) ($tracking['active'] ?? false) && $location !== null;

        $reference = $this->resolveDistanceReference($user, $trip, $studentId);
        $distance = null;

        if ($active && $location !== null && $reference !== null) {
            $meters = Haversine::metersBetween(
                (float) $location['latitude'],
                (float) $location['longitude'],
                (float) $reference['latitude'],
                (float) $reference['longitude'],
            );
            $distance = [
                'meters' => round($meters, 1),
                'km' => round($meters / 1000, 3),
                'reference' => $reference,
            ];
        }

        $prefix = (string) config('realtime.channel_prefix', 'trip_');

        return [
            'trip_id' => TripPublicId::forTrip($trip),
            'trip_history_id' => (int) $trip->id,
            'trip_status' => (string) ($trip->status ?? ''),
            'tracking_active' => $active,
            'driver' => $this->formatDriver($trip),
            'bus' => $this->formatBus($trip),
            'location' => $location,
            'distance' => $distance,
            'realtime' => [
                'firebase_database_url' => config('realtime.firebase.database_url'),
                'firebase_path' => TripLocationPath::locationChildPath((int) $trip->id),
                'firebase_tracking_path' => $this->locations->trackingPath((int) $trip->id),
                'fcm_topic' => $this->topicNamer->topicForTrip($trip),
                'pusher_channel' => 'private-trip.'.$trip->id,
                'topic_template' => $prefix.'{tripId}',
            ],
        ];
    }

    public function deactivate(TripHistory $trip): void
    {
        $tripId = (int) $trip->id;
        $this->locations->deactivate($tripId);
        $this->broadcastLocationUpdate($tripId, [], false);
    }

    /**
     * @param  array<string, mixed>  $location
     */
    private function broadcastLocationUpdate(int $tripHistoryId, array $location, bool $active): void
    {
        if (! config('trips.location_broadcast_enabled', true)) {
            return;
        }

        try {
            DriverLocationUpdated::dispatch($tripHistoryId, $location, $active);
        } catch (Throwable $e) {
            Log::warning('Driver location broadcast failed', [
                'trip_history_id' => $tripHistoryId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function assertDriverOwnsActiveTrip(Driver $driver, TripHistory $trip): void
    {
        if ((int) ($trip->driver_id ?? 0) !== (int) $driver->id) {
            throw ValidationException::withMessages([
                'trip' => ['You are not assigned to this trip.'],
            ]);
        }

        $status = strtoupper((string) ($trip->status ?? ''));
        if (in_array($status, ['CANCELLED', 'COMPLETED'], true)) {
            throw ValidationException::withMessages([
                'trip' => ['This trip is not active.'],
            ]);
        }

        if ($trip->driver_started_at === null) {
            throw ValidationException::withMessages([
                'trip' => ['Start the trip before sending location updates.'],
            ]);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveDistanceReference(User $user, TripHistory $trip, ?int $studentId): ?array
    {
        if ($studentId !== null) {
            $student = $this->resolveStudentReference($user, $trip, $studentId);

            return $student;
        }

        $studentIds = ParentContext::studentIdsFor($user);
        if ($studentIds !== []) {
            $onTrip = $trip->tripHistoryStudents()
                ->whereIn('student_id', $studentIds)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->first();

            if ($onTrip) {
                $student = Student::query()->find($onTrip->student_id);
                if ($student && $student->latitude !== null && $student->longitude !== null) {
                    return $this->studentReference($student);
                }
            }
        }

        $school = $trip->school;
        if ($school instanceof School && $school->latitude !== null && $school->longitude !== null) {
            return [
                'type' => 'school',
                'school_id' => (int) $school->id,
                'latitude' => (float) $school->latitude,
                'longitude' => (float) $school->longitude,
                'label' => (string) ($school->name_en ?: $school->name_ar),
            ];
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveStudentReference(User $user, TripHistory $trip, int $studentId): ?array
    {
        if (! $user->is_admin && ! ParentContext::ownsStudent($user, $studentId)) {
            throw ValidationException::withMessages([
                'student_id' => ['Student not found or not accessible.'],
            ]);
        }

        $onTrip = $trip->tripHistoryStudents()->where('student_id', $studentId)->exists();
        if (! $onTrip) {
            throw ValidationException::withMessages([
                'student_id' => ['Student is not on this trip.'],
            ]);
        }

        $student = Student::query()->find($studentId);
        if (! $student || $student->latitude === null || $student->longitude === null) {
            return null;
        }

        return $this->studentReference($student);
    }

    /**
     * @return array<string, mixed>
     */
    private function studentReference(Student $student): array
    {
        return [
            'type' => 'student_pickup',
            'student_id' => TripPublicId::forStudent((int) $student->id),
            'student_history_id' => (int) $student->id,
            'latitude' => (float) $student->latitude,
            'longitude' => (float) $student->longitude,
            'label' => trim((string) $student->full_name),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function formatDriver(TripHistory $trip): ?array
    {
        $driver = $trip->driver;
        if (! $driver instanceof Driver) {
            return null;
        }

        $name = trim(implode(' ', array_filter([
            $driver->first_name,
            $driver->father_name,
            $driver->grandfather_name,
            $driver->last_name,
        ])));

        return [
            'id' => (int) $driver->id,
            'name' => $name,
            'first_name' => $driver->first_name,
            'father_name' => $driver->father_name,
            'grandfather_name' => $driver->grandfather_name,
            'last_name' => $driver->last_name,
            'primary_phone' => $driver->primary_phone,
            'emergency_phone' => $driver->emergency_phone,
            'license_number' => $driver->license_number,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function formatBus(TripHistory $trip): ?array
    {
        $bus = $trip->driver?->bus;
        if (! $bus instanceof Bus) {
            if ($trip->bus_number) {
                return [
                    'number' => (string) $trip->bus_number,
                ];
            }

            return null;
        }

        return [
            'id' => (int) $bus->id,
            'name' => $bus->name,
            'number' => $bus->number,
            'type' => $bus->type,
            'capacity' => $bus->capacity,
            'color' => $bus->color,
        ];
    }

    private function maxUpdatesPerMinute(): int
    {
        return max(6, (int) config('trips.location_max_updates_per_minute', 30));
    }
}
