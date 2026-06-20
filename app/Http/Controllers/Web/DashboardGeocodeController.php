<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Geo\GoogleReverseGeocoder;
use App\Services\Geo\NominatimReverseGeocoder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardGeocodeController extends Controller
{
    public function reverse(
        Request $request,
        GoogleReverseGeocoder $googleGeocoder,
        NominatimReverseGeocoder $nominatimGeocoder,
    ): JsonResponse {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $latitude = (float) $validated['latitude'];
        $longitude = (float) $validated['longitude'];
        $language = app()->getLocale();

        $result = $googleGeocoder->resolve($latitude, $longitude, $language)
            ?? $nominatimGeocoder->resolve($latitude, $longitude, $language);

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
