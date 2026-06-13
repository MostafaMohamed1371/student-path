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

        $location = $this->homeLocationService->syncForUser(
            $user,
            $latitude,
            $longitude,
            $formattedAddress,
            $districtArea,
            $nearestLandmark,
        );

        $this->afterHomeLocationSaved($user, $location);
    }

    public function afterHomeLocationSaved(User $user, HomeLocation $location): void
    {
        if ($location->latitude === null || $location->longitude === null) {
            return;
        }

        $landmark = trim((string) ($location->nearest_landmark ?? $location->formatted_address ?? ''));
        $district = trim((string) ($location->district_area ?? ''));
        if ($district === '' && $landmark !== '') {
            $district = $landmark;
        }

        $this->syncStudentsForUser(
            $user,
            (float) $location->latitude,
            (float) $location->longitude,
            $district !== '' ? $district : null,
            $landmark !== '' ? $landmark : null,
        );
    }

    public function syncStudentsForUser(
        User $user,
        float $latitude,
        float $longitude,
        ?string $districtArea = null,
        ?string $nearestLandmark = null,
    ): void {
        $guardianIds = $this->guardianIdsForUser($user);
        if ($guardianIds === []) {
            return;
        }

        $payload = [
            'latitude' => $latitude,
            'longitude' => $longitude,
        ];

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
