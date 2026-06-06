<?php

namespace App\Services\Guardian;

use App\Models\Guardian;
use App\Models\HomeLocation;
use App\Models\User;
use App\Services\HomeLocation\HomeLocationService;
use App\Services\Phone\PhoneNormalizer;

final class GuardianHomeLocationSync
{
    public function __construct(
        private readonly PhoneNormalizer $phoneNormalizer,
        private readonly HomeLocationService $homeLocationService,
    ) {}

    public function homeLocationForGuardian(Guardian $guardian): ?HomeLocation
    {
        $user = $this->userForGuardian($guardian);

        return $user?->homeLocation;
    }

    public function hasHomeLocationForGuardian(Guardian $guardian): bool
    {
        return $this->homeLocationService->hasLocation($this->homeLocationForGuardian($guardian));
    }

    /**
     * @return array{
     *     home_latitude: float,
     *     home_longitude: float,
     *     home_district_area: string|null,
     *     home_nearest_landmark: string|null,
     *     home_formatted_address: string|null
     * }|array{}
     */
    public function homeLocationFieldsForGuardian(Guardian $guardian): array
    {
        $home = $this->homeLocationForGuardian($guardian);
        if ($home === null || $home->latitude === null || $home->longitude === null) {
            return [];
        }

        $landmark = (string) ($home->nearest_landmark ?? $home->formatted_address ?? '');
        $district = (string) ($home->district_area ?? '');

        return [
            'home_latitude' => (float) $home->latitude,
            'home_longitude' => (float) $home->longitude,
            'home_district_area' => $district !== '' ? $district : null,
            'home_nearest_landmark' => $landmark !== '' ? $landmark : null,
            'home_formatted_address' => $landmark !== '' ? $landmark : $home->formatted_address,
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

    public function syncForUser(
        User $user,
        ?float $latitude,
        ?float $longitude,
        ?string $formattedAddress,
        ?string $districtArea = null,
        ?string $nearestLandmark = null,
    ): void {
        if ($latitude === null || $longitude === null) {
            return;
        }

        $this->homeLocationService->syncForUser(
            $user,
            $latitude,
            $longitude,
            $formattedAddress,
            $districtArea,
            $nearestLandmark,
        );
    }
}
