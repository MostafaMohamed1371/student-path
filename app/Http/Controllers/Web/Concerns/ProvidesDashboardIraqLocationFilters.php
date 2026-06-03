<?php

namespace App\Http\Controllers\Web\Concerns;

use App\Models\Area;
use App\Models\District;
use App\Models\Neighborhood;
use App\Services\Routes\RouteIraqLocationFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

trait ProvidesDashboardIraqLocationFilters
{
    /**
     * @return array{
     *     showIraqLocationFilter: bool,
     *     filterDistrictId: int,
     *     filterAreaId: int,
     *     filterNeighborhoodId: int,
     *     governorates: Collection<int, District>,
     *     filterAreas: Collection<int, Area>,
     *     filterNeighborhoods: Collection<int, Neighborhood>
     * }
     */
    protected function iraqLocationFilterContext(Request $request): array
    {
        $filterDistrictId = (int) $request->query('district_id', 0);
        $filterAreaId = (int) $request->query('area_id', 0);
        $filterNeighborhoodId = (int) $request->query('neighborhood_id', 0);

        if ($filterNeighborhoodId > 0) {
            $neighborhood = Neighborhood::query()->with('area')->find($filterNeighborhoodId);
            if (! $neighborhood || ! $neighborhood->area) {
                $filterNeighborhoodId = 0;
                $filterAreaId = 0;
                $filterDistrictId = 0;
            } else {
                $filterAreaId = (int) $neighborhood->area_id;
                $filterDistrictId = (int) $neighborhood->area->district_id;
            }
        } elseif ($filterAreaId > 0) {
            $area = Area::query()->find($filterAreaId);
            if (! $area) {
                $filterAreaId = 0;
                $filterDistrictId = 0;
            } else {
                $filterDistrictId = (int) $area->district_id;
            }
        } elseif ($filterDistrictId > 0) {
            if (! District::query()->whereKey($filterDistrictId)->exists()) {
                $filterDistrictId = 0;
            }
        }

        $governorates = District::query()->orderBy('sort_order')->orderBy('name')->get();

        $filterAreas = $filterDistrictId > 0
            ? Area::query()
                ->where('district_id', $filterDistrictId)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
            : collect();

        $filterNeighborhoods = collect();
        if ($filterAreaId > 0) {
            $filterNeighborhoods = Neighborhood::query()
                ->where('area_id', $filterAreaId)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();
        } elseif ($filterDistrictId > 0) {
            $filterNeighborhoods = Neighborhood::query()
                ->whereHas('area', fn (Builder $q) => $q->where('district_id', $filterDistrictId))
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();
        }

        return [
            'showIraqLocationFilter' => true,
            'filterDistrictId' => $filterDistrictId,
            'filterAreaId' => $filterAreaId,
            'filterNeighborhoodId' => $filterNeighborhoodId,
            'governorates' => $governorates,
            'filterAreas' => $filterAreas,
            'filterNeighborhoods' => $filterNeighborhoods,
        ];
    }

    /**
     * @return array{
     *     governorates: Collection<int, District>,
     *     filterAreas: Collection<int, Area>,
     *     filterNeighborhoods: Collection<int, Neighborhood>,
     *     filterDistrictId: int,
     *     filterAreaId: int,
     *     filterNeighborhoodId: int
     * }
     */
    protected function iraqLocationFormContext(int $districtId, int $areaId, int $neighborhoodId): array
    {
        if ($neighborhoodId > 0) {
            $neighborhood = Neighborhood::query()->with('area')->find($neighborhoodId);
            if ($neighborhood?->area) {
                $areaId = (int) $neighborhood->area_id;
                $districtId = (int) $neighborhood->area->district_id;
            }
        } elseif ($areaId > 0) {
            $area = Area::query()->find($areaId);
            if ($area) {
                $districtId = (int) $area->district_id;
            }
        }

        $governorates = District::query()->orderBy('sort_order')->orderBy('name')->get();

        $filterAreas = $districtId > 0
            ? Area::query()->where('district_id', $districtId)->orderBy('sort_order')->orderBy('name')->get()
            : collect();

        $filterNeighborhoods = collect();
        if ($areaId > 0) {
            $filterNeighborhoods = Neighborhood::query()
                ->where('area_id', $areaId)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();
        } elseif ($districtId > 0) {
            $filterNeighborhoods = Neighborhood::query()
                ->whereHas('area', fn (Builder $q) => $q->where('district_id', $districtId))
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();
        }

        return [
            'governorates' => $governorates,
            'filterAreas' => $filterAreas,
            'filterNeighborhoods' => $filterNeighborhoods,
            'filterDistrictId' => $districtId,
            'filterAreaId' => $areaId,
            'filterNeighborhoodId' => $neighborhoodId,
        ];
    }

    /**
     * @param  Builder<\App\Models\TransportRoute>  $query
     * @param  array<string, mixed>  $filters
     */
    protected function applyTransportRouteLocationFilter(Builder $query, array $filters): void
    {
        app(RouteIraqLocationFilter::class)->apply(
            $query,
            (int) ($filters['filterDistrictId'] ?? 0),
            (int) ($filters['filterAreaId'] ?? 0),
            (int) ($filters['filterNeighborhoodId'] ?? 0),
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function normalizeRouteLocationFields(array $validated): array
    {
        $neighborhoodId = isset($validated['neighborhood_id']) ? (int) $validated['neighborhood_id'] : 0;
        $areaId = isset($validated['area_id']) ? (int) $validated['area_id'] : 0;
        $districtId = isset($validated['district_id']) ? (int) $validated['district_id'] : 0;

        if ($neighborhoodId > 0) {
            $neighborhood = Neighborhood::query()->with('area')->find($neighborhoodId);
            if ($neighborhood?->area) {
                $validated['neighborhood_id'] = $neighborhoodId;
                $validated['area_id'] = (int) $neighborhood->area_id;
                $validated['district_id'] = (int) $neighborhood->area->district_id;

                return $validated;
            }
        }

        if ($areaId > 0) {
            $area = Area::query()->find($areaId);
            if ($area) {
                $validated['area_id'] = $areaId;
                $validated['district_id'] = (int) $area->district_id;
                $validated['neighborhood_id'] = null;

                return $validated;
            }
        }

        if ($districtId > 0 && District::query()->whereKey($districtId)->exists()) {
            $validated['district_id'] = $districtId;
            $validated['area_id'] = null;
            $validated['neighborhood_id'] = null;

            return $validated;
        }

        $validated['district_id'] = null;
        $validated['area_id'] = null;
        $validated['neighborhood_id'] = null;

        return $validated;
    }
}
