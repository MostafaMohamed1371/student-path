<?php

namespace App\Services\Guardian;

use App\Models\Guardian;
use App\Support\IdCardNumber;

final class GuardianSchoolProvisioner
{
    public function findForSchoolByIdCard(int $schoolId, string $normalizedIdCard): ?Guardian
    {
        if ($normalizedIdCard === '') {
            return null;
        }

        return Guardian::query()
            ->where('school_id', $schoolId)
            ->where('id_card_number', $normalizedIdCard)
            ->first();
    }

    public function findAnyByIdCard(string $normalizedIdCard): ?Guardian
    {
        if ($normalizedIdCard === '') {
            return null;
        }

        return Guardian::query()
            ->where('id_card_number', $normalizedIdCard)
            ->orderBy('id')
            ->first();
    }

    /**
     * Ensure a guardian roster row exists for the given school (clone from another school when needed).
     */
    public function ensureForSchool(int $schoolId, Guardian $source): Guardian
    {
        if ((int) $source->school_id === $schoolId) {
            return $source;
        }

        $normalized = IdCardNumber::normalize($source->id_card_number);
        if ($normalized !== null) {
            $existing = $this->findForSchoolByIdCard($schoolId, $normalized);
            if ($existing !== null) {
                return $existing;
            }
        }

        return Guardian::query()->create([
            'school_id' => $schoolId,
            'full_name' => $source->full_name,
            'phone' => $source->phone,
            'backup_phone' => $source->backup_phone,
            'id_card_number' => $source->id_card_number,
            'status' => $source->status,
        ]);
    }
}
