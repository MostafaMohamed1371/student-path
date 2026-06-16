<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Neighborhood;
use App\Support\GeoDistance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardLocationController extends Controller
{
    public function areas(Request $request): JsonResponse
    {
        $districtId = (int) $request->query('district_id', 0);
        if ($districtId <= 0) {
            return response()->json(['areas' => []]);
        }

        $areas = Area::query()
            ->where('district_id', $districtId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'district_id', 'name']);

        return response()->json([
            'areas' => $areas->map(fn (Area $a): array => [
                'id' => (int) $a->id,
                'district_id' => (int) $a->district_id,
                'name' => $a->name,
            ])->values()->all(),
        ]);
    }

    public function neighborhoods(Request $request): JsonResponse
    {
        $areaId = (int) $request->query('area_id', 0);
        $districtId = (int) $request->query('district_id', 0);

        $query = Neighborhood::query()->orderBy('sort_order')->orderBy('name');

        if ($areaId > 0) {
            $query->where('area_id', $areaId);
        } elseif ($districtId > 0) {
            $query->whereHas('area', fn ($q) => $q->where('district_id', $districtId));
        } else {
            return response()->json(['neighborhoods' => []]);
        }

        $withCoordinates = $request->boolean('with_coordinates');
        $columns = ['id', 'area_id', 'name'];
        if ($withCoordinates) {
            $columns[] = 'latitude';
            $columns[] = 'longitude';
        }

        $rows = $withCoordinates
            ? $query->with('area:id,district_id')->get($columns)
            : $query->get($columns);

        return response()->json([
            'neighborhoods' => $rows->map(function (Neighborhood $n) use ($withCoordinates): array {
                $payload = [
                    'id' => (int) $n->id,
                    'area_id' => (int) $n->area_id,
                    'name' => $n->name,
                ];

                if ($withCoordinates) {
                    $payload['latitude'] = $n->latitude;
                    $payload['longitude'] = $n->longitude;
                    $payload['district_id'] = (int) ($n->relationLoaded('area') ? $n->area?->district_id : 0);
                }

                return $payload;
            })->values()->all(),
        ]);
    }

    public function resolveNeighborhood(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'district_id' => ['nullable', 'integer', 'min:1'],
            'area_id' => ['nullable', 'integer', 'min:1'],
            'max_radius_km' => ['nullable', 'numeric', 'min:0.1', 'max:50'],
        ]);

        $lat = (float) $validated['latitude'];
        $lng = (float) $validated['longitude'];
        $districtId = (int) ($validated['district_id'] ?? 0);
        $areaId = (int) ($validated['area_id'] ?? 0);
        $maxRadiusKm = (float) ($validated['max_radius_km'] ?? 8);

        $query = Neighborhood::query()
            ->with('area:id,district_id')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude');

        if ($areaId > 0) {
            $query->where('area_id', $areaId);
        } elseif ($districtId > 0) {
            $query->whereHas('area', fn ($q) => $q->where('district_id', $districtId));
        }

        $best = null;
        $bestDistanceKm = PHP_FLOAT_MAX;

        foreach ($query->get(['id', 'area_id', 'name', 'latitude', 'longitude']) as $neighborhood) {
            $distanceKm = GeoDistance::haversineKm(
                $lat,
                $lng,
                (float) $neighborhood->latitude,
                (float) $neighborhood->longitude,
            );

            if ($distanceKm < $bestDistanceKm) {
                $bestDistanceKm = $distanceKm;
                $best = $neighborhood;
            }
        }

        if ($best === null || $bestDistanceKm > $maxRadiusKm) {
            return response()->json(['neighborhood' => null]);
        }

        return response()->json([
            'neighborhood' => [
                'id' => (int) $best->id,
                'area_id' => (int) $best->area_id,
                'district_id' => (int) $best->area->district_id,
                'name' => $best->name,
                'latitude' => (float) $best->latitude,
                'longitude' => (float) $best->longitude,
                'distance_km' => round($bestDistanceKm, 3),
            ],
        ]);
    }
}
