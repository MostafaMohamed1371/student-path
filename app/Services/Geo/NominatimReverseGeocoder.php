<?php

namespace App\Services\Geo;

use Illuminate\Support\Facades\Http;

final class NominatimReverseGeocoder
{
    /**
     * @return array{address: string, province: string|null, district: string|null}|null
     */
    public function resolve(float $latitude, float $longitude, ?string $language = null): ?array
    {
        $language = $language !== null && trim($language) !== ''
            ? trim($language)
            : (string) app()->getLocale();

        $response = Http::timeout(8)
            ->withHeaders([
                'User-Agent' => $this->userAgent(),
                'Accept' => 'application/json',
                'Accept-Language' => $language,
            ])
            ->get('https://nominatim.openstreetmap.org/reverse', [
                'lat' => $latitude,
                'lon' => $longitude,
                'format' => 'json',
                'addressdetails' => 1,
            ]);

        if (! $response->successful()) {
            return null;
        }

        $json = $response->json();
        if (! is_array($json)) {
            return null;
        }

        return $this->mapResponse($json);
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array{address: string, province: string|null, district: string|null}|null
     */
    private function mapResponse(array $json): ?array
    {
        $display = trim((string) ($json['display_name'] ?? ''));
        if ($display === '') {
            return null;
        }

        /** @var array<string, mixed> $parts */
        $parts = is_array($json['address'] ?? null) ? $json['address'] : [];

        $streetLine = $this->joinNonEmpty([
            $parts['house_number'] ?? null,
            $parts['road'] ?? null,
            $parts['neighbourhood'] ?? null,
            $parts['suburb'] ?? null,
        ]);

        $address = $streetLine !== '' ? $streetLine : $display;

        $province = $this->firstNonEmptyString([
            $parts['state'] ?? null,
            $parts['province'] ?? null,
            $parts['region'] ?? null,
        ]);

        $district = $this->firstNonEmptyString([
            $parts['suburb'] ?? null,
            $parts['neighbourhood'] ?? null,
            $parts['district'] ?? null,
            $parts['county'] ?? null,
            $parts['city_district'] ?? null,
        ]);

        return [
            'address' => $address,
            'province' => $province,
            'district' => $district,
        ];
    }

    /**
     * @param  list<mixed>  $values
     */
    private function joinNonEmpty(array $values): string
    {
        $chunks = [];
        foreach ($values as $value) {
            $text = trim((string) ($value ?? ''));
            if ($text !== '') {
                $chunks[] = $text;
            }
        }

        return implode(', ', $chunks);
    }

    /**
     * @param  list<mixed>  $values
     */
    private function firstNonEmptyString(array $values): ?string
    {
        foreach ($values as $value) {
            $text = trim((string) ($value ?? ''));
            if ($text !== '') {
                return $text;
            }
        }

        return null;
    }

    private function userAgent(): string
    {
        $name = trim((string) config('app.name', 'Student-Path'));

        return $name.' Student-Path Dashboard (contact: support@student-path.local)';
    }
}
