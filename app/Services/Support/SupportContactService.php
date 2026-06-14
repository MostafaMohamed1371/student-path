<?php

namespace App\Services\Support;

use App\Models\School;
use App\Models\User;
use App\Support\ParentContext;

final class SupportContactService
{
    /**
     * @return array<string, mixed>
     */
    public function contactMethodsFor(?School $school = null): array
    {
        $methods = config('mobile_legacy_api.support.contact_methods', []);

        if (! $school instanceof School) {
            return $methods;
        }

        $phone = trim((string) ($school->complaints_support_phone ?? ''));
        if ($phone !== '') {
            $methods['phone'] = array_merge($methods['phone'] ?? [], [
                'number' => $phone,
            ]);

            $hours = trim((string) ($school->complaints_support_hours ?? ''));
            if ($hours !== '') {
                $methods['phone']['workingHours'] = $hours;
            }
        }

        $whatsapp = trim((string) ($school->complaints_support_whatsapp ?? ''));
        if ($whatsapp !== '') {
            $methods['whatsapp'] = array_merge($methods['whatsapp'] ?? [], [
                'number' => $whatsapp,
            ]);
        }

        return $methods;
    }

    public function schoolForUser(?User $user): ?School
    {
        if (! $user instanceof User) {
            return null;
        }

        $schoolId = $user->scopingSchoolId();
        if ($schoolId === null) {
            $schoolId = ParentContext::studentsFor($user)
                ->pluck('school_id')
                ->map(fn ($id): int => (int) $id)
                ->filter(fn (int $id): bool => $id > 0)
                ->first();
        }

        if ($schoolId === null || $schoolId <= 0) {
            return null;
        }

        return School::query()->find($schoolId);
    }
}
