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

        $json = $this->fetchReverse($latitude, $longitude, $language);
        if ($json === null) {
            return null;
        }

        $result = $this->mapResponse($json);
        if ($result === null) {
            return null;
        }

        if (! $this->responseHasPlaceName($json)) {
            $poiJson = $this->fetchReverse($latitude, $longitude, $language, layer: 'poi');
            if ($poiJson !== null && $this->responseHasPlaceName($poiJson)) {
                $result['address'] = $this->prependPlaceName(
                    $this->resolvePlaceName($poiJson),
                    $result['address'],
                );
            }
        }

        return $result;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchReverse(
        float $latitude,
        float $longitude,
        string $language,
        ?string $layer = null,
    ): ?array {
        $query = [
            'lat' => $latitude,
            'lon' => $longitude,
            'format' => 'json',
            'addressdetails' => 1,
            'namedetails' => 1,
            'extratags' => 1,
            'zoom' => 18,
        ];

        if ($layer !== null && $layer !== '') {
            $query['layer'] = $layer;
        }

        $response = Http::timeout(8)
            ->withHeaders([
                'User-Agent' => $this->userAgent(),
                'Accept' => 'application/json',
                'Accept-Language' => $language,
            ])
            ->get('https://nominatim.openstreetmap.org/reverse', $query);

        if (! $response->successful()) {
            return null;
        }

        $json = $response->json();

        return is_array($json) ? $json : null;
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

        $address = $this->formatAddressLine($json, $parts, $display);

        $province = $this->firstNonEmptyString([
            $parts['state'] ?? null,
            $parts['province'] ?? null,
            $parts['region'] ?? null,
        ]);

        $district = $this->firstNonEmptyString([
            $parts['suburb'] ?? null,
            $parts['quarter'] ?? null,
            $parts['neighbourhood'] ?? null,
            $parts['subdistrict'] ?? null,
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
     * @param  array<string, mixed>  $json
     * @param  array<string, mixed>  $parts
     */
    private function formatAddressLine(array $json, array $parts, string $display): string
    {
        $road = $this->cleanPart($parts['road'] ?? null);
        $houseNumber = $this->cleanPart($parts['house_number'] ?? null);
        $poiLabel = $this->resolvePlaceName($json, $parts, $display, $road);

        $chunks = [];
        if ($poiLabel !== '' && ! $this->equalsIgnoreCase($poiLabel, $road)) {
            $chunks[] = $poiLabel;
        }

        if ($road !== '') {
            $chunks[] = $houseNumber !== '' ? trim($houseNumber.' '.$road) : $road;
        } elseif ($houseNumber !== '') {
            $chunks[] = $houseNumber;
        }

        foreach (['neighbourhood', 'quarter', 'suburb'] as $key) {
            $value = $this->cleanPart($parts[$key] ?? null);
            if ($value !== '' && ! $this->equalsIgnoreCase($value, $poiLabel)) {
                $chunks[] = $value;
            }
        }

        $line = $this->joinUnique($chunks);

        return $line !== '' ? $line : $display;
    }

    /**
     * @param  array<string, mixed>  $json
     * @param  array<string, mixed>  $parts
     */
    private function resolvePlaceName(
        array $json,
        array $parts = [],
        string $display = '',
        string $road = '',
    ): string {
        $candidates = [
            $this->cleanPart($json['name'] ?? null),
            $this->nameFromNamedetails($json),
            $this->cleanPart($parts['name'] ?? null),
            $this->cleanPart($parts['house_name'] ?? null),
            $this->labelFromTaggedField($parts['amenity'] ?? null),
            $this->labelFromTaggedField($parts['building'] ?? null),
            $this->labelFromTaggedField($parts['shop'] ?? null),
            $this->labelFromTaggedField($parts['office'] ?? null),
            $this->labelFromTaggedField($parts['tourism'] ?? null),
            $this->labelFromTaggedField($parts['historic'] ?? null),
            $this->labelFromTaggedField($parts['man_made'] ?? null),
            $this->labelFromTaggedField($parts['public_building'] ?? null),
            $this->meaningfulDisplayLead($display, $road),
        ];

        foreach ($candidates as $candidate) {
            if ($candidate !== '' && ! $this->equalsIgnoreCase($candidate, $road)) {
                return $candidate;
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $json
     */
    private function responseHasPlaceName(array $json): bool
    {
        /** @var array<string, mixed> $parts */
        $parts = is_array($json['address'] ?? null) ? $json['address'] : [];
        $road = $this->cleanPart($parts['road'] ?? null);

        return $this->resolvePlaceName($json, $parts, (string) ($json['display_name'] ?? ''), $road) !== '';
    }

    /**
     * @param  array<string, mixed>  $json
     */
    private function nameFromNamedetails(array $json): string
    {
        $namedetails = is_array($json['namedetails'] ?? null) ? $json['namedetails'] : [];

        foreach (['name', 'name:ar', 'name:en', 'official_name', 'short_name'] as $key) {
            $name = $this->cleanPart($namedetails[$key] ?? null);
            if ($name !== '') {
                return $name;
            }
        }

        return '';
    }

    private function labelFromTaggedField(mixed $value): string
    {
        $text = $this->cleanPart($value);
        if ($text === '' || $this->isGenericPlaceTag($text)) {
            return '';
        }

        return $text;
    }

    private function isGenericPlaceTag(string $value): bool
    {
        $normalized = mb_strtolower($text = trim($value));

        if (in_array($normalized, [
            'yes', 'true', '1', 'no',
            'house', 'residential', 'commercial', 'retail', 'industrial', 'apartments',
            'school', 'kindergarten', 'hospital', 'clinic', 'pharmacy',
            'mosque', 'place_of worship', 'place_of_worship', 'church',
            'building', 'construction', 'garage', 'shed', 'roof', 'warehouse',
            'fountain', 'yes', 'detached', 'terrace', 'semidetached_house',
        ], true)) {
            return true;
        }

        // Short single-word OSM type values are usually tags, not building names.
        return ! str_contains($text, ' ') && mb_strlen($text) <= 12;
    }

    private function meaningfulDisplayLead(string $display, string $road): string
    {
        $segments = array_values(array_filter(array_map(
            fn ($part) => trim((string) $part),
            explode(',', $display),
        ), fn ($part) => $part !== ''));

        if ($segments === []) {
            return '';
        }

        $lead = $segments[0];
        if ($lead === '' || $this->isGenericPlaceTag($lead)) {
            return '';
        }

        if ($road !== '' && $this->equalsIgnoreCase($lead, $road)) {
            return '';
        }

        if (preg_match('/^\d+$/', $lead) === 1) {
            return '';
        }

        return $lead;
    }

    private function prependPlaceName(string $placeName, string $address): string
    {
        $placeName = trim($placeName);
        $address = trim($address);

        if ($placeName === '') {
            return $address;
        }

        if ($address === '' || str_starts_with(mb_strtolower($address), mb_strtolower($placeName))) {
            return $placeName.($address !== '' ? ', '.$address : '');
        }

        return $placeName.', '.$address;
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
18    }

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
