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

        $language = $this->resolvedPlacesLanguage();
        if ($language !== '') {
            $query['language'] = $language;
        }

        $region = (string) config('google.places_region', '');
        if ($region !== '') {
            $query['region'] = $region;
        }

        $components = (string) config('google.places_components', '');
        if ($components !== '') {
            $query['components'] = $components;
        }

        $response = Http::timeout(10)->get(
            'https://maps.googleapis.com/maps/api/place/autocomplete/json',
            $query
        );

        if (! $response->successful()) {
            return $this->parentError('Places request failed.', null, 502);
        }

        $json = $response->json();
        if (! is_array($json)) {
            return $this->parentError('Places response was invalid.', null, 502);
        }

        $failure = $this->googlePlacesFailureResponse($json, isDetails: false);
        if ($failure !== null) {
            return $failure;
        }

        return $this->parentSuccess($json);
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

        $fields = isset($validated['fields']) && $validated['fields'] !== ''
            ? $validated['fields']
            : (string) config('google.places_details_default_fields', '');
        if ($fields !== '') {
            $query['fields'] = $fields;
        }

        $language = $this->resolvedPlacesLanguage();
        if ($language !== '') {
            $query['language'] = $language;
        }

        $response = Http::timeout(10)->get(
            'https://maps.googleapis.com/maps/api/place/details/json',
            $query
        );

        if (! $response->successful()) {
            return $this->parentError('Places request failed.', null, 502);
        }

        $json = $response->json();
        if (! is_array($json)) {
            return $this->parentError('Places response was invalid.', null, 502);
        }

        $failure = $this->googlePlacesFailureResponse($json, isDetails: true);
        if ($failure !== null) {
            return $failure;
        }

        return $this->parentSuccess($json);
    }

    private function resolvedPlacesLanguage(): string
    {
        $fromConfig = trim((string) config('google.places_language', ''));
        if ($fromConfig !== '') {
            return $fromConfig;
        }

        $locale = (string) config('app.locale', 'en');

        return explode('_', $locale, 2)[0];
    }

    /**
     * @param  array<string, mixed>  $json
     */
    private function googlePlacesFailureResponse(array $json, bool $isDetails): ?JsonResponse
    {
        $status = (string) ($json['status'] ?? '');
        if ($status === 'OK' || $status === 'ZERO_RESULTS') {
            return null;
        }

        $googleErrorMessage = isset($json['error_message']) && is_string($json['error_message'])
            ? $json['error_message']
            : '';

        $meta = array_filter([
            'google_status' => $status,
            'google_error_message' => $googleErrorMessage !== '' ? $googleErrorMessage : null,
        ]);

        if ($isDetails && $status === 'NOT_FOUND') {
            return $this->parentError('Place not found.', null, 404, $meta !== [] ? $meta : null);
        }

        return match ($status) {
            'INVALID_REQUEST' => $this->parentError(
                'Invalid Places request.',
                null,
                400,
                $meta !== [] ? $meta : null
            ),
            'OVER_QUERY_LIMIT' => $this->parentError(
                'Places quota exceeded.',
                null,
                503,
                $meta !== [] ? $meta : null
            ),
            'REQUEST_DENIED' => $this->parentError(
                'Places request denied. Check the API key and enabled Google APIs.',
                null,
                503,
                $meta !== [] ? $meta : null
            ),
            default => $this->parentError(
                'Places request failed.',
                null,
                502,
                $meta !== [] ? $meta : null
            ),
        };
    }
}
