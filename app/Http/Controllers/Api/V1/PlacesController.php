<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\FormatsParentApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PlacesController extends Controller
{
    use FormatsParentApiResponse;

    public function autocomplete(Request $request): JsonResponse
    {
        $key = (string) config('google.places_api_key');
        if ($key === '') {
            return $this->parentError('Google Places is not configured.', null, 503);
        }

        $validated = $request->validate([
            'input' => ['required', 'string', 'min:1', 'max:500'],
            'sessiontoken' => ['nullable', 'string', 'max:255'],
        ]);

        $query = [
            'input' => $validated['input'],
            'key' => $key,
            'types' => 'geocode',
        ];
        if (! empty($validated['sessiontoken'])) {
            $query['sessiontoken'] = $validated['sessiontoken'];
        }

        $response = Http::timeout(10)->get(
            'https://maps.googleapis.com/maps/api/place/autocomplete/json',
            $query
        );

        if (! $response->successful()) {
            return $this->parentError('Places request failed.', null, 502);
        }

        return $this->parentSuccess($response->json());
    }

    public function details(Request $request, string $place): JsonResponse
    {
        $key = (string) config('google.places_api_key');
        if ($key === '') {
            return $this->parentError('Google Places is not configured.', null, 503);
        }

        $validated = $request->validate([
            'sessiontoken' => ['nullable', 'string', 'max:255'],
            'fields' => ['nullable', 'string', 'max:500'],
        ]);

        $query = [
            'place_id' => $place,
            'key' => $key,
        ];
        if (! empty($validated['sessiontoken'])) {
            $query['sessiontoken'] = $validated['sessiontoken'];
        }
        if (! empty($validated['fields'])) {
            $query['fields'] = $validated['fields'];
        }

        $response = Http::timeout(10)->get(
            'https://maps.googleapis.com/maps/api/place/details/json',
            $query
        );

        if (! $response->successful()) {
            return $this->parentError('Places request failed.', null, 502);
        }

        return $this->parentSuccess($response->json());
    }
}
