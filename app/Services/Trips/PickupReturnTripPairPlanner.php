<?php

namespace App\Services\Trips;

use App\Enums\TripType;
use App\Models\School;
use App\Models\TripHistory;
use App\Support\Geo\Haversine;
use Illuminate\Support\Carbon;

final class PickupReturnTripPairPlanner
{
    /**
     * @param  array<string, mixed>  $pickupAttributes
     * @return array<string, mixed>|null
     */
    public function returnTripAttributesFromPickup(array $pickupAttributes, School $school): ?array
    {
        $pickupType = TripType::tryFrom(trim((string) ($pickupAttributes['trip_type'] ?? '')));
        $returnType = $pickupType?->pairedReturnType();
        if ($returnType === null) {
            return null;
        }

        $pickupStart = $this->asCarbon($pickupAttributes['start_time'] ?? null);
        $pickupEnd = $this->asCarbon($pickupAttributes['end_time'] ?? null);
        if ($pickupStart === null || $pickupEnd === null) {
            return null;
        }

        $returnStart = $pickupEnd->copy();
        $durationMinutes = max(1, $pickupStart->diffInMinutes($pickupEnd));
        $returnEnd = $returnStart->copy()->addMinutes($durationMinutes);

        $pickupStartAddress = trim((string) ($pickupAttributes['start_address'] ?? ''));
        $schoolAddress = trim((string) ($school->address ?? ''));

        $schoolLat = $school->latitude !== null ? (float) $school->latitude : null;
        $schoolLng = $school->longitude !== null ? (float) $school->longitude : null;

        $pickupStartLat = isset($pickupAttributes['start_latitude']) && $pickupAttributes['start_latitude'] !== ''
            ? (float) $pickupAttributes['start_latitude']
            : null;
        $pickupStartLng = isset($pickupAttributes['start_longitude']) && $pickupAttributes['start_longitude'] !== ''
            ? (float) $pickupAttributes['start_longitude']
            : null;

        $distanceKm = $pickupAttributes['distance_km'] ?? null;
        if (
            ($distanceKm === null || (float) $distanceKm <= 0)
            && $schoolLat !== null
            && $schoolLng !== null
            && $pickupStartLat !== null
            && $pickupStartLng !== null
        ) {
            $distanceKm = round(
                Haversine::metersBetween($schoolLat, $schoolLng, $pickupStartLat, $pickupStartLng) / 1000,
                2,
            );
        }

        $pickupRouteTitle = trim((string) ($pickupAttributes['route_title'] ?? ''));

        return [
            'school_id' => (int) ($pickupAttributes['school_id'] ?? 0),
            'driver_id' => isset($pickupAttributes['driver_id']) ? (int) $pickupAttributes['driver_id'] : null,
            'trip_type' => $returnType->value,
            'bus_number' => (string) ($pickupAttributes['bus_number'] ?? ''),
            'route_title' => $this->returnRouteTitle($pickupRouteTitle, $returnType),
            'location' => $this->locationLabel($schoolAddress, $pickupStartAddress),
            'start_address' => $schoolAddress !== '' ? $schoolAddress : null,
            'start_latitude' => $schoolLat,
            'start_longitude' => $schoolLng,
            'students_count' => 0,
            'distance_km' => $distanceKm ?? 0,
            'start_time' => $returnStart,
            'end_time' => $returnEnd,
            'status' => (string) ($pickupAttributes['status'] ?? 'PRESENT'),
            'note' => $this->returnTripNote($pickupAttributes['note'] ?? null),
            'students_preview' => [],
        ];
    }

    public function returnTripExistsForPickup(TripHistory $pickupTrip, string $returnTripType): bool
    {
        if ($pickupTrip->end_time === null || $pickupTrip->driver_id === null) {
            return false;
        }

        return TripHistory::query()
            ->where('driver_id', (int) $pickupTrip->driver_id)
            ->where('school_id', (int) $pickupTrip->school_id)
            ->where('trip_type', $returnTripType)
            ->whereDate('start_time', $pickupTrip->end_time->toDateString())
            ->exists();
    }

    private function returnRouteTitle(string $pickupRouteTitle, TripType $returnType): ?string
    {
        $suffix = match ($returnType) {
            TripType::MORNING_RETURN => __('dashboard.trip_return_route_title_morning'),
            TripType::EVENING_RETURN => __('dashboard.trip_return_route_title_evening'),
            default => __('dashboard.trip_return_route_title_generic'),
        };

        if ($pickupRouteTitle === '') {
            return $suffix;
        }

        return $pickupRouteTitle.' — '.$suffix;
    }

    private function returnTripNote(mixed $pickupNote): ?string
    {
        $suffix = __('dashboard.trip_return_auto_created_note');
        $pickupNote = is_string($pickupNote) ? trim($pickupNote) : '';

        if ($pickupNote === '') {
            return $suffix;
        }

        return $pickupNote.' — '.$suffix;
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

    private function asCarbon(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->copy();
        }

        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }
}
