<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Geo\NominatimReverseGeocoder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardGeocodeController extends Controller
{
    public function reverse(Request $request, NominatimReverseGeocoder $geocoder): JsonResponse
    {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $result = $geocoder->resolve(
            (float) $validated['latitude'],
            (float) $validated['longitude'],
            app()->getLocale(),
        );

        if ($result === null) {
            return response()->json([
                'success' => false,
                'message' => __('dashboard.school_map_address_not_found'),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }
}
