<?php

namespace App\Services\Geo;

use Illuminate\Support\Facades\Http;

final class GoogleReverseGeocoder
{
    /**
     * @return array{address: string, province: string|null, district: string|null}|null
     */
    public function resolve(float $latitude, float $longitude, ?string $language = null): ?array
    {
        $key = (string) config('google.geocoding_api_key');
        if ($key === '') {
            return null;
        }

        $language = $language !== null && trim($language) !== ''
            ? trim($language)
            : (string) app()->getLocale();

        $response = Http::timeout(10)
            ->get('https://maps.googleapis.com/maps/api/geocode/json', [
                'latlng' => $latitude.','.$longitude,
                'key' => $key,
                'language' => $language,
                'region' => (string) config('google.places_region', 'iq'),
            ]);

        if (! $response->successful()) {
            return null;
        }

        /** @var array<string, mixed> $json */
        $json = $response->json();
        if (($json['status'] ?? '') !== 'OK' || ! is_array($json['results'] ?? null) || $json['results'] === []) {
            return null;
        }

        /** @var array<string, mixed> $result */
        $result = $json['results'][0];
        $components = is_array($result['address_components'] ?? null) ? $result['address_components'] : [];
        $formattedAddress = trim((string) ($result['formatted_address'] ?? ''));

        if ($formattedAddress === '') {
            return null;
        }

        return [
            'address' => $formattedAddress,
            'province' => $this->componentValue($components, ['administrative_area_level_1']),
            'district' => $this->componentValue($components, [
                'locality',
                'administrative_area_level_2',
                'sublocality',
                'sublocality_level_1',
                'neighborhood',
            ]),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $components
     * @param  list<string>  $types
     */
    private function componentValue(array $components, array $types): ?string
    {
        foreach ($types as $type) {
            foreach ($components as $component) {
                $componentTypes = is_array($component['types'] ?? null) ? $component['types'] : [];
                if (! in_array($type, $componentTypes, true)) {
                    continue;
                }

                $longName = trim((string) ($component['long_name'] ?? ''));
                if ($longName !== '') {
                    return $longName;
                }
            }
        }

        return null;
    }
}
