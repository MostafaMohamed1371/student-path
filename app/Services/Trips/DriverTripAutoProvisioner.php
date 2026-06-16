<?php

namespace App\Services\Trips;

use App\Enums\TripType;
use App\Models\Driver;
use App\Models\School;
use App\Models\TripHistory;
use App\Services\Drivers\DriverServiceAreaTripFormatter;
use App\Support\Geo\Haversine;
use App\Support\SchoolWorkSchedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * When a school adds or updates a driver, provision today's pickup + return trips
 * using the same route/schedule rules as the dashboard trip form.
 */
final class DriverTripAutoProvisioner
{
    public function __construct(
        private readonly SchoolWorkSchedule $schoolWorkSchedule,
        private readonly TripTransportRouteApplier $routeApplier,
        private readonly DriverServiceAreaTripFormatter $serviceAreaFormatter,
        private readonly PickupReturnTripPairPlanner $pairPlanner,
        private readonly TripRecurringScheduleService $recurringSchedule,
    ) {}

    /**
     * @return list<TripHistory>
     */
    public function syncForDriver(Driver $driver): array
    {
        $driver->loadMissing(['school', 'bus', 'serviceAreas']);

        if (strtolower(trim((string) ($driver->status ?? ''))) !== 'active') {
            return [];
        }

        $school = $driver->school;
        if (! $school instanceof School) {
            return [];
        }

        $tz = (string) (config('app.timezone') ?: 'UTC');
        $day = now($tz)->startOfDay();

        if (! $this->schoolWorkSchedule->isOpenOn($school, $day)) {
            return [];
        }

        $pickupTypes = $this->pickupTypesForDriverShift((string) ($driver->shift_period ?? ''));
        if ($pickupTypes === []) {
            return [];
        }

        $trips = [];

        foreach ($pickupTypes as $pickupType) {
            $synced = $this->syncPickupAndReturnForDay($driver, $school, $pickupType, $day);
            if ($synced !== []) {
                array_push($trips, ...$synced);
            }
        }

        return $trips;
    }

    /**
     * @return list<TripHistory>
     */
    private function syncPickupAndReturnForDay(
        Driver $driver,
        School $school,
        TripType $pickupType,
        Carbon $day,
    ): array {
        $pickupAttributes = $this->buildPickupAttributes($driver, $school, $pickupType, $day);
        if ($pickupAttributes === null) {
            return [];
        }

        return DB::transaction(function () use ($driver, $school, $pickupType, $day, $pickupAttributes): array {
            $pickupTrip = $this->upsertPickupTrip($driver, $school, $pickupType, $day, $pickupAttributes);
            $returnTrip = $this->upsertReturnTripForPickup($pickupTrip, $pickupAttributes, $school);

            $synced = [$pickupTrip];
            if ($returnTrip instanceof TripHistory) {
                $synced[] = $returnTrip;
            }

            foreach ($synced as $trip) {
                $this->recurringSchedule->syncTrip($trip->fresh(['school', 'tripHistoryStudents']));
            }

            return array_map(
                fn (TripHistory $trip): TripHistory => $trip->fresh(['school', 'tripHistoryStudents']),
                $synced,
            );
        });
    }

    /**
     * @param  array<string, mixed>  $pickupAttributes
     */
    private function upsertPickupTrip(
        Driver $driver,
        School $school,
        TripType $pickupType,
        Carbon $day,
        array $pickupAttributes,
    ): TripHistory {
        $existing = $this->findPickupTripForDay($driver, $school, $pickupType, $day);

        if ($existing instanceof TripHistory && $this->isClosedTrip($existing)) {
            return $existing;
        }

        if ($existing instanceof TripHistory) {
            $existing->update($this->mutablePickupAttributes($pickupAttributes, $existing));

            return $existing->fresh() ?? $existing;
        }

        return TripHistory::query()->create($pickupAttributes);
    }

    /**
     * @param  array<string, mixed>  $pickupAttributes
     */
    private function upsertReturnTripForPickup(
        TripHistory $pickupTrip,
        array $pickupAttributes,
        School $school,
    ): ?TripHistory {
        $returnAttributes = $this->pairPlanner->returnTripAttributesFromPickup($pickupAttributes, $school);
        if ($returnAttributes === null) {
            return null;
        }

        $returnType = (string) $returnAttributes['trip_type'];
        $existing = $this->pairPlanner->findReturnTripForPickup($pickupTrip, $returnType);

        if ($existing instanceof TripHistory && $this->isClosedTrip($existing)) {
            return $existing;
        }

        if ($existing instanceof TripHistory) {
            $existing->update($this->mutableReturnAttributes($returnAttributes, $existing));

            return $existing->fresh() ?? $existing;
        }

        return TripHistory::query()->create($returnAttributes);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildPickupAttributes(
        Driver $driver,
        School $school,
        TripType $pickupType,
        Carbon $day,
    ): ?array {
        $startTime = $this->schoolWorkSchedule->pickupStartTimeForShift($school, $pickupType, $day);
        $endTime = $this->schoolWorkSchedule->pickupEndTimeForShift($school, $pickupType, $day);

        if ($startTime === null || $endTime === null) {
            return null;
        }

        $tripTypeValue = $pickupType->value;
        $base = [
            'school_id' => (int) $school->id,
            'driver_id' => (int) $driver->id,
            'trip_type' => $tripTypeValue,
            'bus_number' => $this->busNumberForDriver($driver),
            'students_count' => 0,
            'students_preview' => [],
            'start_time' => $startTime,
            'end_time' => $endTime,
            'status' => 'PRESENT',
            'auto_schedule_work_days' => true,
            'distance_km' => 0,
        ];

        $base = $this->routeApplier->applyRouteToTripAttributes($base, overwrite: false);

        $route = $this->routeApplier->findRouteForTrip($base);
        if ($route !== null) {
            $payload = $this->routeApplier->driverRouteFormPayload($route);
            if ($payload !== null) {
                $base['route_title'] = $payload['route_title'];
                $base['location'] = $payload['location'];
                $base['distance_km'] = $payload['distance_km'] ?? 0;
                $base['start_address'] = $payload['start_address'];
                if ($payload['route_start_latitude'] !== null) {
                    $base['start_latitude'] = $payload['route_start_latitude'];
                }
                if ($payload['route_start_longitude'] !== null) {
                    $base['start_longitude'] = $payload['route_start_longitude'];
                }
            }
        } else {
            $serviceAreaIds = $driver->serviceAreas->pluck('id')->map(fn ($id): int => (int) $id)->all();
            if ($serviceAreaIds !== []) {
                $combined = $this->serviceAreaFormatter->combineForTrip($serviceAreaIds, $school);
                if (trim($combined['route_title']) !== '') {
                    $base['route_title'] = $combined['route_title'];
                }
                if ($combined['location'] !== null) {
                    $base['location'] = $combined['location'];
                }
                if ($combined['distance_km'] !== null) {
                    $base['distance_km'] = $combined['distance_km'];
                }
                if ($combined['start_address'] !== null) {
                    $base['start_address'] = $combined['start_address'];
                }

                foreach ($this->serviceAreaFormatter->serviceAreasForDriver((int) $driver->id) as $area) {
                    if ($area['latitude'] !== null && $area['longitude'] !== null) {
                        $base['start_latitude'] = (float) $area['latitude'];
                        $base['start_longitude'] = (float) $area['longitude'];
                        break;
                    }
                }
            }
        }

        if (
            isset($base['start_latitude'], $base['start_longitude'])
            && $school->latitude !== null
            && $school->longitude !== null
        ) {
            $base['distance_km'] = round(
                Haversine::metersBetween(
                    (float) $base['start_latitude'],
                    (float) $base['start_longitude'],
                    (float) $school->latitude,
                    (float) $school->longitude,
                ) / 1000,
                2,
            );

            $startAddress = trim((string) ($base['start_address'] ?? ''));
            $endAddress = trim((string) ($school->address ?? ''));
            $base['location'] = $this->locationLabel($startAddress, $endAddress);
        }

        return $base;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function mutablePickupAttributes(array $attributes, TripHistory $existing): array
    {
        unset($attributes['students_count'], $attributes['students_preview'], $attributes['status']);

        if ($this->isClosedTrip($existing)) {
            return [];
        }

        if (in_array(strtoupper((string) $existing->status), ['ACTIVE'], true)) {
            unset($attributes['start_time'], $attributes['end_time']);
        }

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function mutableReturnAttributes(array $attributes, TripHistory $existing): array
    {
        unset($attributes['students_count'], $attributes['students_preview'], $attributes['status']);

        if ($this->isClosedTrip($existing)) {
            return [];
        }

        if (in_array(strtoupper((string) $existing->status), ['ACTIVE'], true)) {
            unset($attributes['start_time'], $attributes['end_time']);
        }

        return $attributes;
    }

    private function findPickupTripForDay(
        Driver $driver,
        School $school,
        TripType $pickupType,
        Carbon $day,
    ): ?TripHistory {
        return TripHistory::query()
            ->where('driver_id', (int) $driver->id)
            ->where('school_id', (int) $school->id)
            ->where('trip_type', $pickupType->value)
            ->whereDate('start_time', $day->toDateString())
            ->orderByDesc('is_recurring_template')
            ->orderBy('id')
            ->first();
    }

    /**
     * @return list<TripType>
     */
    private function pickupTypesForDriverShift(string $shiftPeriod): array
    {
        return match (strtoupper(trim($shiftPeriod))) {
            DriverShiftResolver::MORNING => [TripType::MORNING_PICKUP],
            DriverShiftResolver::EVENING => [TripType::EVENING_PICKUP],
            'BOTH' => [TripType::MORNING_PICKUP, TripType::EVENING_PICKUP],
            default => [],
        };
    }

    private function busNumberForDriver(Driver $driver): string
    {
        $number = trim((string) ($driver->bus?->number ?? ''));

        return $number !== '' ? $number : '—';
    }

    private function isClosedTrip(TripHistory $trip): bool
    {
        return in_array(strtoupper((string) ($trip->status ?? '')), ['CANCELLED', 'COMPLETED'], true);
    }

    private function locationLabel(string $start, string $end): ?string
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
}
