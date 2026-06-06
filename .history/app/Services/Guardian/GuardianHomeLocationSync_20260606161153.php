<?php

namespace App\Services\Guardian;

use App\Models\Area;
use App\Models\Guardian;
use App\Models\HomeLocation;
use App\Models\Neighborhood;
use App\Models\User;
use App\Services\Phone\PhoneNormalizer;

final class GuardianHomeLocationSync
{
    public function __construct(
        private readonly PhoneNormalizer $phoneNormalizer,
    ) {}

    public function homeLocationForGuardian(Guardian $guardian): ?HomeLocation
    {
        $user = $this->userForGuardian($guardian);

        return $user?->homeLocation?->loadMissing(['neighborhoods', 'area', 'district']);
    }

    /**
     * @return array{
     *     home_latitude: float,
     *     home_longitude: float,
     *     home_district_area: string|null,
     *     home_nearest_landmark: string|null,
     *     home_formatted_address: string|null,
     *     home_district_id: int|null,
     *     home_area_id: int|null,
     *     home_neighborhood_ids: list<int>
     * }|array{}
     */
    public function homeLocationFieldsForGuardian(Guardian $guardian): array
    {
        $home = $this->homeLocationForGuardian($guardian);
        if ($home === null || $home->latitude === null || $home->longitude === null) {
            return [];
        }

        $landmark = (string) ($home->nearest_landmark ?? $home->formatted_address ?? '');
        $district = (string) ($home->district_area ?? $home->area?->name ?? '');

        return [
            'home_latitude' => (float) $home->latitude,
            'home_longitude' => (float) $home->longitude,
            'home_district_area' => $district !== '' ? $district : null,
            'home_nearest_landmark' => $landmark !== '' ? $landmark : null,
            'home_formatted_address' => $landmark !== '' ? $landmark : $home->formatted_address,
            'home_district_id' => $home->district_id ? (int) $home->district_id : null,
            'home_area_id' => $home->area_id ? (int) $home->area_id : null,
            'home_neighborhood_ids' => $home->neighborhoods->pluck('id')->map(fn ($id) => (int) $id)->all(),
        ];
    }

    private function userForGuardian(Guardian $guardian): ?User
    {
        $user = User::query()->where('guardian_id', $guardian->id)->first();
        if ($user !== null) {
            return $user;
        }

        $phone = trim((string) $guardian->phone);
        if ($phone === '' || ! $this->phoneNormalizer->isValidIraqiMobile($phone)) {
            return null;
        }

        return User::query()
            ->where('phone', $this->phoneNormalizer->normalize($phone))
            ->first();
    }

    /**
     * @param  list<int>  $neighborhoodIds
     */
    public function syncForUser(
        User $user,
        ?float $latitude,
        ?float $longitude,
        ?string $formattedAddress,
        ?string $districtArea = null,
        ?string $nearestLandmark = null,
        ?int $districtId = null,
        ?int $areaId = null,
        array $neighborhoodIds = [],
    ): void {
        if ($latitude === null || $longitude === null) {
            return;
        }

        $resolved = $this->resolveAddressLabels($districtArea, $nearestLandmark, $formattedAddress, $districtId, $areaId, $neighborhoodIds);

        $home = HomeLocation::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'district_id' => $districtId,
                'area_id' => $areaId,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'formatted_address' => $resolved['landmark'] !== '' ? $resolved['landmark'] : $formattedAddress,
                'district_area' => $resolved['district'] !== '' ? $resolved['district'] : null,
                'nearest_landmark' => $resolved['landmark'] !== '' ? $resolved['landmark'] : null,
            ],
        );

        $home->neighborhoods()->sync($neighborhoodIds);
    }

    /**
     * @param  list<int>  $neighborhoodIds
     * @return array{district: string, landmark: string}
     */
    private function resolveAddressLabels(
        ?string $districtArea,
        ?string $nearestLandmark,
        ?string $formattedAddress,
        ?int $districtId,
        ?int $areaId,
        array $neighborhoodIds,
    ): array {
        $district = trim((string) ($districtArea ?? ''));
        $landmark = trim((string) ($nearestLandmark ?? $formattedAddress ?? ''));

        if ($district === '' && $areaId !== null && $areaId > 0) {
            $district = (string) (Area::query()->whereKey($areaId)->value('name') ?? '');
        }

        if ($landmark === '' && $neighborhoodIds !== []) {
            $landmark = Neighborhood::query()
                ->whereIn('id', $neighborhoodIds)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->pluck('name')
                ->implode(', ');
        }

        if ($district === '' && $landmark !== '') {
            $district = $landmark;
        }

        return [
            'district' => $district,
            'landmark' => $landmark,
        ];
    }
}
