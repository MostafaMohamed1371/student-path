<?php

namespace App\Services\Guardian;

use App\Models\Guardian;
use App\Models\HomeLocation;
use App\Models\Student;
use App\Models\User;
use App\Services\HomeLocation\HomeLocationService;
use App\Services\Phone\PhoneNormalizer;
use App\Support\IdCardNumber;

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
     *     home_district_id: int|null,
     *     home_area_id: int|null,
     *     home_neighborhood_id: int|null,
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
            'home_district_id' => $home->district_id !== null ? (int) $home->district_id : null,
            'home_area_id' => $home->area_id !== null ? (int) $home->area_id : null,
            'home_neighborhood_id' => $home->neighborhood_id !== null ? (int) $home->neighborhood_id : null,
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
        ?int $districtId = null,
        ?int $areaId = null,
        ?int $neighborhoodId = null,
    ): void {
        $hasCoordinates = $latitude !== null && $longitude !== null;
        $hasIraqLocation = ($districtId !== null && $districtId > 0)
            || ($areaId !== null && $areaId > 0)
            || ($neighborhoodId !== null && $neighborhoodId > 0);

        if (! $hasCoordinates && ! $hasIraqLocation) {
            return;
        }

        $location = $this->homeLocationService->syncForUser(
            $user,
            $latitude,
            $longitude,
            $formattedAddress,
            $districtArea,
            $nearestLandmark,
            null,
            $districtId,
            $areaId,
            $neighborhoodId,
        );

        $this->afterHomeLocationSaved($user, $location);
    }

    public function afterHomeLocationSaved(User $user, HomeLocation $location): void
    {
        if ($location->latitude === null || $location->longitude === null) {
            if ($location->district_id === null && $location->area_id === null && $location->neighborhood_id === null) {
                return;
            }
        }

        $landmark = trim((string) ($location->nearest_landmark ?? $location->formatted_address ?? ''));
        $district = trim((string) ($location->district_area ?? ''));
        if ($district === '' && $landmark !== '') {
            $district = $landmark;
        }

        if ($location->latitude !== null && $location->longitude !== null) {
            $this->syncStudentsForUser(
                $user,
                (float) $location->latitude,
                (float) $location->longitude,
                $district !== '' ? $district : null,
                $landmark !== '' ? $landmark : null,
                $location->district_id !== null ? (int) $location->district_id : null,
                $location->area_id !== null ? (int) $location->area_id : null,
                $location->neighborhood_id !== null ? (int) $location->neighborhood_id : null,
            );

            return;
        }

        $guardianIds = $this->guardianIdsForUser($user);
        if ($guardianIds === []) {
            return;
        }

        $payload = [];
        if ($location->district_id !== null) {
            $payload['district_id'] = (int) $location->district_id;
        }
        if ($location->area_id !== null) {
            $payload['area_id'] = (int) $location->area_id;
        }
        if ($location->neighborhood_id !== null) {
            $payload['neighborhood_id'] = (int) $location->neighborhood_id;
        }
        if ($district !== '') {
            $payload['district_area'] = $district;
        }
        if ($landmark !== '') {
            $payload['nearest_landmark'] = $landmark;
        }

        if ($payload !== []) {
            Student::query()->whereIn('guardian_id', $guardianIds)->update($payload);
        }
    }

    public function syncStudentsForUser(
        User $user,
        float $latitude,
        float $longitude,
        ?string $districtArea = null,
        ?string $nearestLandmark = null,
        ?int $districtId = null,
        ?int $areaId = null,
        ?int $neighborhoodId = null,
    ): void {
        $guardianIds = $this->guardianIdsForUser($user);
        if ($guardianIds === []) {
            return;
        }

        $payload = [
            'latitude' => $latitude,
            'longitude' => $longitude,
        ];

        if ($districtId !== null && $districtId > 0) {
            $payload['district_id'] = $districtId;
        }
        if ($areaId !== null && $areaId > 0) {
            $payload['area_id'] = $areaId;
        }
        if ($neighborhoodId !== null && $neighborhoodId > 0) {
            $payload['neighborhood_id'] = $neighborhoodId;
        }

        if ($districtArea !== null && trim($districtArea) !== '') {
            $payload['district_area'] = trim($districtArea);
        }

        if ($nearestLandmark !== null && trim($nearestLandmark) !== '') {
            $payload['nearest_landmark'] = trim($nearestLandmark);
        }

        Student::query()
            ->whereIn('guardian_id', $guardianIds)
            ->update($payload);
    }

    /**
     * @return list<int>
     */
    private function guardianIdsForUser(User $user): array
    {
        $ids = [];

        if ($user->guardian_id !== null && (int) $user->guardian_id > 0) {
            $ids[] = (int) $user->guardian_id;
        }

        $phone = trim((string) ($user->phone ?? ''));
        if ($phone !== '') {
            $national = preg_replace('/\D+/', '', $phone) ?? '';
            if (str_starts_with($national, '964')) {
                $national = substr($national, 3);
            }

            $phoneMatches = array_values(array_unique(array_filter([
                $national,
                $this->phoneNormalizer->isValidIraqiMobile($national)
                    ? $this->phoneNormalizer->normalize($national)
                    : null,
            ])));

            if ($phoneMatches !== []) {
                $ids = array_merge(
                    $ids,
                    Guardian::query()
                        ->whereIn('phone', $phoneMatches)
                        ->pluck('id')
                        ->map(fn ($id): int => (int) $id)
                        ->all(),
                );
            }
        }

        $ids = array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }

        $idCards = Guardian::query()
            ->whereIn('id', $ids)
            ->pluck('id_card_number')
            ->map(fn ($card): ?string => IdCardNumber::normalize($card))
            ->filter(fn (?string $card): bool => is_string($card) && $card !== '')
            ->unique()
            ->values()
            ->all();

        foreach ($idCards as $idCard) {
            $ids = array_merge(
                $ids,
                Guardian::query()
                    ->where('id_card_number', $idCard)
                    ->pluck('id')
                    ->map(fn ($id): int => (int) $id)
                    ->all(),
            );
        }

        return array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));
    }
}
