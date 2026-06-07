<?php

namespace App\Http\Controllers\Web\Concerns;

use App\Models\Area;
use App\Models\Driver;
use App\Models\Neighborhood;

trait SyncsDriverServiceAreas
{
    /**
     * @return list<array<string, mixed>>
     */
    protected function driverServiceAreaRowsForForm(?Driver $driver): array
    {
        $oldRows = old('service_areas');
        if (is_array($oldRows) && $oldRows !== []) {
            return $this->enrichServiceAreaRows($oldRows);
        }

        if ($driver instanceof Driver) {
            $driver->loadMissing(['serviceAreas.neighborhoods', 'neighborhoods']);

            if ($driver->serviceAreas->isNotEmpty()) {
                return $this->enrichServiceAreaRows(
                    $driver->serviceAreas->map(fn ($row): array => [
                        'district_id' => $row->district_id,
                        'area_id' => $row->area_id,
                        'neighborhood_ids' => $row->neighborhoods->pluck('id')->all(),
                        'monthly_subscription_price' => $row->monthly_subscription_price,
                    ])->all(),
                );
            }

            if ($driver->district_id || $driver->area_id || $driver->neighborhoods->isNotEmpty() || $driver->monthly_subscription_price !== null) {
                return $this->enrichServiceAreaRows([[
                    'district_id' => $driver->district_id,
                    'area_id' => $driver->area_id,
                    'neighborhood_ids' => $driver->neighborhoods->pluck('id')->all(),
                    'monthly_subscription_price' => $driver->monthly_subscription_price,
                ]]);
            }
        }

        return $this->enrichServiceAreaRows([[
            'district_id' => '',
            'area_id' => '',
            'neighborhood_ids' => [],
            'monthly_subscription_price' => '',
        ]]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    protected function syncDriverServiceAreas(Driver $driver, array $rows): void
    {
        $normalized = collect($rows)
            ->values()
            ->map(function (array $row, int $index): ?array {
                $districtId = (int) ($row['district_id'] ?? 0);
                $areaId = (int) ($row['area_id'] ?? 0);
                $neighborhoodIds = $this->parsedServiceAreaNeighborhoodIds($row);
                $priceRaw = $row['monthly_subscription_price'] ?? null;
                $price = ($priceRaw !== null && $priceRaw !== '') ? (int) $priceRaw : null;

                if ($districtId <= 0 && $areaId <= 0 && $neighborhoodIds === [] && $price === null) {
                    return null;
                }

                if ($neighborhoodIds !== []) {
                    $neighborhoods = Neighborhood::query()->with('area')->whereIn('id', $neighborhoodIds)->get();
                    if ($areaId > 0) {
                        $neighborhoodIds = $neighborhoods
                            ->where('area_id', $areaId)
                            ->pluck('id')
                            ->map(fn ($id): int => (int) $id)
                            ->values()
                            ->all();
                    }

                    $first = $neighborhoods->first();
                    if ($first?->area) {
                        $areaId = (int) $first->area_id;
                        $districtId = (int) $first->area->district_id;
                    }
                } elseif ($areaId > 0) {
                    $area = Area::query()->find($areaId);
                    if ($area) {
                        $districtId = (int) $area->district_id;
                    }
                }

                return [
                    'attributes' => [
                        'district_id' => $districtId > 0 ? $districtId : null,
                        'area_id' => $areaId > 0 ? $areaId : null,
                        'monthly_subscription_price' => $price,
                        'sort_order' => $index,
                    ],
                    'neighborhood_ids' => $neighborhoodIds,
                ];
            })
            ->filter()
            ->values();

        $driver->serviceAreas()->delete();

        $allNeighborhoodIds = [];
        foreach ($normalized as $row) {
            $serviceArea = $driver->serviceAreas()->create($row['attributes']);
            $serviceArea->neighborhoods()->sync($row['neighborhood_ids']);
            $allNeighborhoodIds = array_merge($allNeighborhoodIds, $row['neighborhood_ids']);
        }

        $allNeighborhoodIds = array_values(array_unique(array_map('intval', $allNeighborhoodIds)));
        $first = $normalized->first();

        if ($first === null) {
            $driver->forceFill([
                'district_id' => null,
                'area_id' => null,
                'monthly_subscription_price' => null,
            ])->save();
            $this->syncModelNeighborhoods($driver, []);

            return;
        }

        $driver->forceFill([
            'district_id' => $first['attributes']['district_id'] ?? null,
            'area_id' => $first['attributes']['area_id'] ?? null,
            'monthly_subscription_price' => $first['attributes']['monthly_subscription_price'] ?? null,
        ])->save();

        $this->syncModelNeighborhoods($driver, $allNeighborhoodIds);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<int>
     */
    private function parsedServiceAreaNeighborhoodIds(array $row): array
    {
        $raw = $row['neighborhood_ids'] ?? $row['neighborhood_id'] ?? [];

        if (! is_array($raw)) {
            $raw = [(int) $raw];
        }

        return collect($raw)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function enrichServiceAreaRows(array $rows): array
    {
        return collect($rows)
            ->values()
            ->map(function (array $row): array {
                $districtId = (int) ($row['district_id'] ?? 0);
                $areaId = (int) ($row['area_id'] ?? 0);
                $selectedNeighborhoodIds = collect($this->parsedServiceAreaNeighborhoodIds($row))
                    ->map(fn (int $id): string => (string) $id)
                    ->all();

                $areas = $districtId > 0
                    ? Area::query()->where('district_id', $districtId)->orderBy('sort_order')->orderBy('name')->get()
                    : collect();

                $neighborhoods = collect();
                if ($areaId > 0) {
                    $neighborhoods = Neighborhood::query()
                        ->where('area_id', $areaId)
                        ->orderBy('sort_order')
                        ->orderBy('name')
                        ->get();
                } elseif ($districtId > 0) {
                    $neighborhoods = Neighborhood::query()
                        ->whereHas('area', fn ($q) => $q->where('district_id', $districtId))
                        ->orderBy('sort_order')
                        ->orderBy('name')
                        ->get();
                }

                return [
                    'district_id' => $row['district_id'] ?? '',
                    'area_id' => $row['area_id'] ?? '',
                    'neighborhood_ids' => $selectedNeighborhoodIds,
                    'monthly_subscription_price' => $row['monthly_subscription_price'] ?? '',
                    'areas' => $areas,
                    'neighborhoods' => $neighborhoods,
                ];
            })
            ->all();
    }
}
