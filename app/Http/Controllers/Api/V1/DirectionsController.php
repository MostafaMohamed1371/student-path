<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\FormatsParentApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class DirectionsController extends Controller
{
    use FormatsParentApiResponse;

    public function flat(Request $request): JsonResponse
    {
        $key = (string) config('google.directions_api_key');
        if ($key === '') {
            return $this->parentError('Google Directions is not configured.', null, 503);
        }

        $validated = $request->validate([
            'origin.latitude' => ['required', 'numeric', 'between:-90,90'],
            'origin.longitude' => ['required', 'numeric', 'between:-180,180'],
            'destination.latitude' => ['required', 'numeric', 'between:-90,90'],
            'destination.longitude' => ['required', 'numeric', 'between:-180,180'],
            'mode' => ['nullable', 'string', 'in:driving,walking,bicycling,transit'],
            'units' => ['nullable', 'string', 'in:metric,imperial'],
            'language' => ['nullable', 'string', 'max:16'],
            'alternatives' => ['nullable', 'boolean'],
        ]);

        $origin = $validated['origin'];
        $destination = $validated['destination'];
        $mode = (string) ($validated['mode'] ?? 'driving');

        $query = [
            'origin' => $this->latLngString((float) $origin['latitude'], (float) $origin['longitude']),
            'destination' => $this->latLngString((float) $destination['latitude'], (float) $destination['longitude']),
            'mode' => $mode,
            'units' => (string) ($validated['units'] ?? 'metric'),
            'alternatives' => ((bool) ($validated['alternatives'] ?? false)) ? 'true' : 'false',
            'key' => $key,
        ];

        $language = trim((string) ($validated['language'] ?? $this->resolvedDirectionsLanguage()));
        if ($language !== '') {
            $query['language'] = $language;
        }

        $region = (string) config('google.places_region', '');
        if ($region !== '') {
            $query['region'] = $region;
        }

        $response = Http::timeout(10)->get(
            'https://maps.googleapis.com/maps/api/directions/json',
            $query
        );

        if (! $response->successful()) {
            return $this->parentError('Directions request failed.', null, 502);
        }

        $json = $response->json();
        if (! is_array($json)) {
            return $this->parentError('Directions response was invalid.', null, 502);
        }

        $failure = $this->googleDirectionsFailureResponse($json);
        if ($failure !== null) {
            return $failure;
        }

        return $this->parentSuccess(
            $this->flatDirectionsPayload($json, $origin, $destination, $mode),
            'Directions retrieved successfully.'
        );
    }

    private function resolvedDirectionsLanguage(): string
    {
        $fromConfig = trim((string) config('google.directions_language', ''));
        if ($fromConfig !== '') {
            return $fromConfig;
        }

        $locale = (string) config('app.locale', 'en');

        return explode('_', $locale, 2)[0];
    }

    /**
     * @param  array<string, mixed>  $json
     */
    private function googleDirectionsFailureResponse(array $json): ?JsonResponse
    {
        $status = (string) ($json['status'] ?? '');
        if ($status === 'OK' || $status === 'ZERO_RESULTS') {
            return null;
        }

        $meta = array_filter([
            'google_status' => $status,
            'google_error_message' => isset($json['error_message']) && is_string($json['error_message'])
                ? $json['error_message']
                : null,
        ]);

        return match ($status) {
            'INVALID_REQUEST' => $this->parentError(
                'Invalid Directions request.',
                null,
                400,
                $meta !== [] ? $meta : null
            ),
            'OVER_QUERY_LIMIT', 'OVER_DAILY_LIMIT' => $this->parentError(
                'Directions quota exceeded.',
                null,
                503,
                $meta !== [] ? $meta : null
            ),
            'REQUEST_DENIED' => $this->parentError(
                'Directions request denied. Check the API key and enabled Google APIs.',
                null,
                503,
                $meta !== [] ? $meta : null
            ),
            default => $this->parentError(
                'Directions request failed.',
                null,
                502,
                $meta !== [] ? $meta : null
            ),
        };
    }

    /**
     * @param  array<string, mixed>  $json
     * @param  array<string, mixed>  $origin
     * @param  array<string, mixed>  $destination
     * @return array<string, mixed>
     */
    private function flatDirectionsPayload(array $json, array $origin, array $destination, string $mode): array
    {
        $routes = is_array($json['routes'] ?? null) ? $json['routes'] : [];
        $firstRoute = is_array($routes[0] ?? null) ? $routes[0] : null;

        if ($firstRoute === null) {
            return [
                'google_status' => (string) ($json['status'] ?? 'ZERO_RESULTS'),
                'route_found' => false,
                'origin' => $this->coordinatePayload($origin),
                'destination' => $this->coordinatePayload($destination),
                'mode' => $mode,
                'distance' => null,
                'duration' => null,
                'overview_polyline' => null,
                'bounds' => null,
                'steps' => [],
                'map_url' => $this->googleMapsDirectionsUrl($origin, $destination, $mode),
            ];
        }

        $legs = is_array($firstRoute['legs'] ?? null) ? $firstRoute['legs'] : [];
        $firstLeg = is_array($legs[0] ?? null) ? $legs[0] : [];
        $steps = is_array($firstLeg['steps'] ?? null) ? $firstLeg['steps'] : [];

        return [
            'google_status' => (string) ($json['status'] ?? 'OK'),
            'route_found' => true,
            'origin' => array_merge($this->coordinatePayload($origin), [
                'address' => $firstLeg['start_address'] ?? null,
            ]),
            'destination' => array_merge($this->coordinatePayload($destination), [
                'address' => $firstLeg['end_address'] ?? null,
            ]),
            'mode' => $mode,
            'distance' => $this->metricPayload($firstLeg['distance'] ?? null),
            'duration' => $this->metricPayload($firstLeg['duration'] ?? null),
            'overview_polyline' => data_get($firstRoute, 'overview_polyline.points'),
            'bounds' => $this->boundsPayload($firstRoute['bounds'] ?? null),
            'steps' => array_values(array_map(fn (array $step): array => $this->stepPayload($step), $steps)),
            'map_url' => $this->googleMapsDirectionsUrl($origin, $destination, $mode),
        ];
    }

    /**
     * @param  array<string, mixed>  $step
     * @return array<string, mixed>
     */
    private function stepPayload(array $step): array
    {
        return [
            'instruction' => trim(strip_tags((string) ($step['html_instructions'] ?? ''))),
            'distance' => $this->metricPayload($step['distance'] ?? null),
            'duration' => $this->metricPayload($step['duration'] ?? null),
            'start_location' => $this->googleLocationPayload($step['start_location'] ?? null),
            'end_location' => $this->googleLocationPayload($step['end_location'] ?? null),
            'polyline' => data_get($step, 'polyline.points'),
            'travel_mode' => $step['travel_mode'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>|mixed  $metric
     * @return array{text: string|null, value: int|null}|null
     */
    private function metricPayload(mixed $metric): ?array
    {
        if (! is_array($metric)) {
            return null;
        }

        return [
            'text' => isset($metric['text']) ? (string) $metric['text'] : null,
            'value' => isset($metric['value']) ? (int) $metric['value'] : null,
        ];
    }

    /**
     * @param  array<string, mixed>|mixed  $location
     * @return array{latitude: float, longitude: float}|null
     */
    private function googleLocationPayload(mixed $location): ?array
    {
        if (! is_array($location) || ! isset($location['lat'], $location['lng'])) {
            return null;
        }

        return [
            'latitude' => (float) $location['lat'],
            'longitude' => (float) $location['lng'],
        ];
    }

    /**
     * @param  array<string, mixed>|mixed  $bounds
     * @return array<string, mixed>|null
     */
    private function boundsPayload(mixed $bounds): ?array
    {
        if (! is_array($bounds)) {
            return null;
        }

        return [
            'northeast' => $this->googleLocationPayload($bounds['northeast'] ?? null),
            'southwest' => $this->googleLocationPayload($bounds['southwest'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $coordinate
     * @return array{latitude: float, longitude: float}
     */
    private function coordinatePayload(array $coordinate): array
    {
        return [
            'latitude' => (float) $coordinate['latitude'],
            'longitude' => (float) $coordinate['longitude'],
        ];
    }

    /**
     * @param  array<string, mixed>  $origin
     * @param  array<string, mixed>  $destination
     */
    private function googleMapsDirectionsUrl(array $origin, array $destination, string $mode): string
    {
        return 'https://www.google.com/maps/dir/?'.http_build_query([
            'api' => '1',
            'origin' => $this->latLngString((float) $origin['latitude'], (float) $origin['longitude']),
            'destination' => $this->latLngString((float) $destination['latitude'], (float) $destination['longitude']),
            'travelmode' => $mode,
        ]);
    }

    private function latLngString(float $latitude, float $longitude): string
    {
        return Str::of(number_format($latitude, 7, '.', ''))
            ->rtrim('0')
            ->rtrim('.')
            ->append(',')
            ->append(Str::of(number_format($longitude, 7, '.', ''))->rtrim('0')->rtrim('.'))
            ->toString();
    }
}
