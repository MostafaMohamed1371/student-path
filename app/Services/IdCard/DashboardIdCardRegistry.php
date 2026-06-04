<?php

namespace App\Services\IdCard;

use App\Enums\PhoneAccountType;
use App\Models\Driver;
use App\Models\Guardian;
use App\Support\IdCardNumber;
use Illuminate\Validation\ValidationException;

final class DashboardIdCardRegistry
{
    public function normalize(?string $raw): ?string
    {
        return IdCardNumber::normalize($raw);
    }

    /**
     * @return list<IdCardOccupancy>
     */
    public function occupanciesFor(string $raw): array
    {
        $normalized = $this->normalize($raw);
        if ($normalized === null) {
            return [];
        }

        $occupancies = [];

        foreach (Driver::query()->where('id_card_number', $normalized)->get(['id']) as $driver) {
            $occupancies[] = new IdCardOccupancy(
                'driver',
                (int) $driver->id,
                PhoneAccountType::Driver,
            );
        }

        foreach (Guardian::query()->where('id_card_number', $normalized)->get(['id']) as $guardian) {
            $occupancies[] = new IdCardOccupancy(
                'guardian',
                (int) $guardian->id,
                PhoneAccountType::Guardian,
            );
        }

        return $occupancies;
    }

    /**
     * @throws ValidationException
     */
    public function assertAvailable(
        string $raw,
        ?IdCardRecordIdentity $except = null,
        string $errorAttribute = 'id_card_number',
    ): void {
        $normalized = $this->normalize($raw);
        if ($normalized === null) {
            return;
        }

        foreach ($this->occupanciesFor($normalized) as $occupancy) {
            if ($except !== null && $occupancy->isSameRecordAs($except)) {
                continue;
            }

            if ($this->isGuardianIdCardAllowedAtAnotherSchool($occupancy, $except)) {
                continue;
            }

            throw ValidationException::withMessages([
                $errorAttribute => [
                    __('dashboard.id_card_already_used', [
                        'owner' => $occupancy->accountType->label(),
                    ]),
                ],
            ]);
        }
    }

    private function isGuardianIdCardAllowedAtAnotherSchool(
        IdCardOccupancy $occupancy,
        ?IdCardRecordIdentity $except,
    ): bool {
        if ($except?->guardianSchoolId === null || $occupancy->entity !== 'guardian') {
            return false;
        }

        $other = Guardian::query()->find($occupancy->entityId);
        if ($other === null) {
            return false;
        }

        return (int) $other->school_id !== $except->guardianSchoolId;
    }
}
