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
                'zoom' => 30,
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

        $address = $this->formatAddressLine($parts, $display);

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
     * @param  array<string, mixed>  $parts
     */
    private function formatAddressLine(array $parts, string $display): string
    {
        $name = $this->cleanPart($parts['name'] ?? null);
        $houseName = $this->cleanPart($parts['house_name'] ?? null);
        $building = $this->cleanPart($parts['building'] ?? null);
        $road = $this->cleanPart($parts['road'] ?? null);
        $houseNumber = $this->cleanPart($parts['house_number'] ?? null);

        if (in_array(strtolower($building), ['yes', 'true', '1'], true)) {
            $building = '';
        }

        $poiLabel = $name !== '' ? $name : $houseName;
        if ($poiLabel === '' && $building !== '' && ! $this->equalsIgnoreCase($building, $road)) {
            $poiLabel = $building;
        }

        $chunks = [];
        if ($poiLabel !== '' && ! $this->equalsIgnoreCase($poiLabel, $road)) {
            $chunks[] = $poiLabel;
        }

        if ($road !== '') {
            $chunks[] = $houseNumber !== '' ? trim($houseNumber.' '.$road) : $road;
        } elseif ($houseNumber !== '') {
            $chunks[] = $houseNumber;
        }

        foreach (['neighbourhood', 'suburb'] as $key) {
            $value = $this->cleanPart($parts[$key] ?? null);
            if ($value !== '') {
                $chunks[] = $value;
            }
        }

        $line = $this->joinUnique($chunks);

        return $line !== '' ? $line : $display;
    }

    private function cleanPart(mixed $value): string
    {
        return trim((string) ($value ?? ''));
    }

    private function equalsIgnoreCase(string $left, string $right): bool
    {
        if ($left === '' || $right === '') {
            return false;
        }

        return mb_strtolower($left) === mb_strtolower($right);
    }

    /**
     * @param  list<string>  $chunks
     */
    private function joinUnique(array $chunks): string
    {
        $seen = [];
        $unique = [];

        foreach ($chunks as $chunk) {
            $text = trim($chunk);
            if ($text === '') {
                continue;
            }

            $key = mb_strtolower($text);
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $unique[] = $text;
        }

        return implode(', ', $unique);
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
