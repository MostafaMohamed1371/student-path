<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Neighborhood;
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

        $rows = $query->get(['id', 'area_id', 'name']);

        return response()->json([
            'neighborhoods' => $rows->map(fn (Neighborhood $n): array => [
                'id' => (int) $n->id,
                'area_id' => (int) $n->area_id,
                'name' => $n->name,
            ])->values()->all(),
        ]);
    }
}
