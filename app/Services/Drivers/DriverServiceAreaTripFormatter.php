<?php

namespace App\Services\Drivers;

use App\Models\DriverServiceArea;
use App\Models\School;
use App\Support\Geo\Haversine;
use Illuminate\Support\Collection;

final class DriverServiceAreaTripFormatter
{
    /**
     * @return list<array{
     *     id: int,
     *     label: string,
     *     route_title: string,
     *     start_label: string,
     *     monthly_subscription_price: int|null
     * }>
     */
    public function serviceAreasForDriver(int $driverId): array
    {
        return $this->addressInformationByDriverIds([$driverId])[$driverId] ?? [];
    }

    /**
     * @param  list<int>  $driverIds
     * @return array<int, list<array{
     *     id: int,
     *     label: string,
     *     route_title: string,
     *     start_label: string,
     *     monthly_subscription_price: int|null,
     *     latitude: float|null,
     *     longitude: float|null,
     *     neighborhood_ids: list<int>
     * }>>
     */
    public function addressInformationByDriverIds(array $driverIds): array
    {
        $driverIds = array_values(array_unique(array_filter(
            array_map('intval', $driverIds),
            fn (int $id): bool => $id > 0,
        )));

        if ($driverIds === []) {
            return [];
        }

        $out = array_fill_keys($driverIds, []);

        DriverServiceArea::query()
            ->whereIn('driver_id', $driverIds)
            ->with(['district', 'area', 'neighborhoods'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->each(function (DriverServiceArea $area) use (&$out): void {
                $driverId = (int) $area->driver_id;
                $out[$driverId][] = $this->formatServiceArea($area);
            });

        return $out;
    }

    /**
     * When the parent's sub-district matches at least one driver service area, keep only those rows.
     * Otherwise return the full list (parent is in a different sub-district).
     *
     * @param  list<array<string, mixed>>  $addressInformation
     * @return list<array<string, mixed>>
     */
    public function filterAddressInformationForPickupNeighborhood(array $addressInformation, ?int $pickupNeighborhoodId): array
    {
        if ($pickupNeighborhoodId === null || $pickupNeighborhoodId <= 0 || $addressInformation === []) {
            return $addressInformation;
        }

        $matching = array_values(array_filter(
            $addressInformation,
            fn (array $item): bool => in_array(
                $pickupNeighborhoodId,
                array_map('intval', $item['neighborhood_ids'] ?? []),
                true,
            ),
        ));

        return $matching !== [] ? $matching : $addressInformation;
    }

    /**
     * @param  list<int>  $serviceAreaIds
     * @return array{
     *     route_title: string,
     *     location: string|null,
     *     distance_km: float|null,
     *     start_address: string|null,
     *     end_address: string|null
     * }
     */
    public function combineForTrip(array $serviceAreaIds, ?School $school): array
    {
        $serviceAreaIds = array_values(array_unique(array_filter(array_map('intval', $serviceAreaIds), fn (int $id): bool => $id > 0)));
        if ($serviceAreaIds === []) {
            return [
                'route_title' => '',
                'location' => null,
                'distance_km' => null,
                'start_address' => null,
                'end_address' => null,
            ];
        }

        $areas = DriverServiceArea::query()
            ->whereIn('id', $serviceAreaIds)
            ->with(['district', 'area', 'neighborhoods'])
            ->get()
            ->sortBy(fn (DriverServiceArea $area): int => array_search((int) $area->id, $serviceAreaIds, true))
            ->values();

        $titles = [];
        $starts = [];
        $distances = [];

        foreach ($areas as $area) {
            $formatted = $this->formatServiceArea($area);
            $titles[] = $formatted['route_title'];
            $starts[] = $formatted['start_label'];

            $point = $this->representativePoint($area);
            if ($point !== null && $school?->latitude !== null && $school?->longitude !== null) {
                $distances[] = Haversine::metersBetween(
                    $point[0],
                    $point[1],
                    (float) $school->latitude,
                    (float) $school->longitude,
                ) / 1000;
            }
        }

        $startAddress = implode(', ', array_filter($starts));
        $endAddress = $school ? trim((string) ($school->address ?? '')) : '';

        $location = $this->locationFromStartAndEnd($startAddress, $endAddress);

        $distanceKm = $distances !== []
            ? round(array_sum($distances) / count($distances), 2)
            : null;

        return [
            'route_title' => implode(' | ', array_filter($titles)),
            'location' => $location,
            'distance_km' => $distanceKm,
            'start_address' => $startAddress !== '' ? $startAddress : null,
            'end_address' => $endAddress !== '' ? $endAddress : null,
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     label: string,
     *     route_title: string,
     *     start_label: string,
     *     monthly_subscription_price: int|null,
     *     neighborhood_ids: list<int>
     * }
     */
    public function formatServiceArea(DriverServiceArea $area): array
    {
        $area->loadMissing(['district', 'area', 'neighborhoods']);

        $gov = trim((string) ($area->district?->name ?? ''));
        $district = trim((string) ($area->area?->name ?? ''));
        $subs = $area->neighborhoods
            ->pluck('name')
            ->map(fn ($name): string => trim((string) $name))
            ->filter(fn (string $name): bool => $name !== '')
            ->values()
            ->all();

        $parts = array_values(array_filter([$gov, $district]));
        if ($subs !== []) {
            $parts[] = implode(', ', $subs);
        }

        $startLabel = implode(' / ', $parts);
        if ($startLabel === '') {
            $startLabel = __('dashboard.address_entry').' #'.((int) $area->sort_order + 1);
        }

        $routeTitle = $startLabel;
        $label = $startLabel;
        if ($area->monthly_subscription_price !== null) {
            $price = number_format((int) $area->monthly_subscription_price).' '.__('dashboard.currency_iqd_short');
            $label .= ' ('.$price.')';
        }

        $point = $this->representativePoint($area);

        return [
            'id' => (int) $area->id,
            'label' => $label,
            'route_title' => $routeTitle,
            'start_label' => $startLabel,
            'monthly_subscription_price' => $area->monthly_subscription_price !== null
                ? (int) $area->monthly_subscription_price
                : null,
            'latitude' => $point !== null ? $point[0] : null,
            'longitude' => $point !== null ? $point[1] : null,
            'neighborhood_ids' => $area->neighborhoods
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->values()
                ->all(),
        ];
    }

    private function locationFromStartAndEnd(string $start, string $end): ?string
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

    /**
     * @return array{0: float, 1: float}|null
     */
    private function representativePoint(DriverServiceArea $area): ?array
    {
        /** @var Collection<int, \App\Models\Neighborhood> $neighborhoods */
        $neighborhoods = $area->relationLoaded('neighborhoods')
            ? $area->neighborhoods
            : $area->neighborhoods()->get();

        foreach ($neighborhoods as $neighborhood) {
            if ($neighborhood->latitude !== null && $neighborhood->longitude !== null) {
                return [(float) $neighborhood->latitude, (float) $neighborhood->longitude];
            }
        }

        return null;
    }
}
