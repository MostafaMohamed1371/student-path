<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\FormatsParentApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\IraqLocationsRequest;
use App\Models\Area;
use App\Models\District;
use App\Models\Neighborhood;
use App\Support\GeoDistance;
use Illuminate\Http\JsonResponse;

class LocationController extends Controller
{
    use FormatsParentApiResponse;

    public function iraq(IraqLocationsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $usesClientReference = isset($validated['latitude'], $validated['longitude']);
        $refLat = $usesClientReference
            ? (float) $validated['latitude']
            : (float) config('locations.iraq_distance_reference_latitude');
        $refLng = $usesClientReference
            ? (float) $validated['longitude']
            : (float) config('locations.iraq_distance_reference_longitude');
        $maxRadiusKm = isset($validated['max_radius_km']) ? (float) $validated['max_radius_km'] : null;

        $districts = District::query()
            ->with([
                'areas' => fn ($q) => $q->orderBy('sort_order')->orderBy('name'),
                'areas.neighborhoods' => fn ($q) => $q->orderBy('sort_order')->orderBy('name'),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $payload = $districts->map(function (District $district) use ($refLat, $refLng, $maxRadiusKm): array {
            $areas = $district->areas->map(function (Area $area) use ($refLat, $refLng, $maxRadiusKm): ?array {
                $neighborhoods = $area->neighborhoods->map(function (Neighborhood $n) use ($refLat, $refLng, $maxRadiusKm): ?array {
                    $distanceKm = null;
                    if ($n->latitude !== null && $n->longitude !== null) {
                        $distanceKm = GeoDistance::haversineKm(
                            $refLat,
                            $refLng,
                            (float) $n->latitude,
                            (float) $n->longitude,
                        );
                    }

                    if ($maxRadiusKm !== null && $distanceKm !== null && $distanceKm > $maxRadiusKm) {
                        return null;
                    }

                    return [
                        'id' => $n->id,
                        'name' => $n->name,
                        'latitude' => $n->latitude,
                        'longitude' => $n->longitude,
                        'distance_km' => $distanceKm !== null ? round($distanceKm, 3) : null,
                    ];
                })->filter()->values();

                $sorted = $neighborhoods->sortBy(function (array $row): float {
                    $d = $row['distance_km'];

                    return $d === null ? PHP_FLOAT_MAX : (float) $d;
                })->values()->all();

                if ($sorted === [] && $maxRadiusKm !== null) {
                    return null;
                }

                return [
                    'id' => $area->id,
                    'district_id' => $area->district_id,
                    'name' => $area->name,
                    'neighborhoods' => $sorted,
                ];
            })->filter()->values()->all();

            return [
                'id' => $district->id,
                'name' => $district->name,
                'areas' => $areas,
            ];
        })->filter(function (array $d): bool {
            return $d['areas'] !== [];
        })->values()->all();

        return $this->parentSuccess($payload, 'success', 200, [
            'distance_reference' => [
                'latitude' => $refLat,
                'longitude' => $refLng,
                'source' => $usesClientReference ? 'request' : 'iraq_default',
                'label' => $usesClientReference
                    ? 'Client-provided coordinates'
                    : (string) config('locations.iraq_distance_reference_label'),
            ],
        ]);
    }

    public function districts(): JsonResponse
    {
        $rows = District::query()->orderBy('sort_order')->orderBy('name')->get();

        return $this->parentSuccess(
            $rows->map(fn (District $d) => [
                'id' => $d->id,
                'name' => $d->name,
            ])->values()->all()
        );
    }

    public function areas(District $district): JsonResponse
    {
        $rows = Area::query()
            ->where('district_id', $district->id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return $this->parentSuccess(
            $rows->map(fn (Area $a) => [
                'id' => $a->id,
                'district_id' => $a->district_id,
                'name' => $a->name,
            ])->values()->all()
        );
    }
}
