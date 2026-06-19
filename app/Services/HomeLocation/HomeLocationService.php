<?php

namespace App\Services\HomeLocation;

use App\Models\HomeLocation;
use App\Models\User;

final class HomeLocationService
{
    /**
     * @return array{
     *     latitude: float,
     *     longitude: float,
     *     formatted_address: string|null,
     *     district_area: string|null,
     *     nearest_landmark: string|null,
     *     place_id: string|null,
     *     has_location: true
     * }|null
     */
    public function formatForApi(?HomeLocation $location): ?array
    {
        if ($location === null || $location->latitude === null || $location->longitude === null) {
            return null;
        }

        return [
            'latitude' => (float) $location->latitude,
            'longitude' => (float) $location->longitude,
            'formatted_address' => $location->formatted_address,
            'district_area' => $location->district_area,
            'nearest_landmark' => $location->nearest_landmark,
            'place_id' => $location->place_id,
            'has_location' => true,
        ];
    }

    public function hasLocation(?HomeLocation $location): bool
    {
        return $location !== null
            && $location->latitude !== null
            && $location->longitude !== null;
    }

    public function syncForUser(
        User $user,
        ?float $latitude,
        ?float $longitude,
        ?string $formattedAddress = null,
        ?string $districtArea = null,
        ?string $nearestLandmark = null,
        ?string $placeId = null,
        ?int $districtId = null,
        ?int $areaId = null,
        ?int $neighborhoodId = null,
    ): HomeLocation {
        $landmark = trim((string) ($nearestLandmark ?? $formattedAddress ?? ''));
        $district = trim((string) ($districtArea ?? ''));
        if ($district === '' && $landmark !== '') {
            $district = $landmark;
        }

        $attributes = [
            'district_id' => $districtId,
            'area_id' => $areaId,
            'neighborhood_id' => $neighborhoodId,
            'formatted_address' => $landmark !== '' ? $landmark : $formattedAddress,
            'district_area' => $district !== '' ? $district : null,
            'nearest_landmark' => $landmark !== '' ? $landmark : null,
            'place_id' => $placeId,
        ];

        if ($latitude !== null && $longitude !== null) {
            $attributes['latitude'] = $latitude;
            $attributes['longitude'] = $longitude;
        }

        return HomeLocation::query()->updateOrCreate(
            ['user_id' => $user->id],
            $attributes,
        );
    }

    public function deleteForUser(User $user): void
    {
        HomeLocation::query()->where('user_id', $user->id)->delete();
    }
}
