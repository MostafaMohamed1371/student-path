<?php

namespace App\Support;

use App\Models\Guardian;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Collection;

final class ParentContext
{
    public static function guardian(User $user): ?Guardian
    {
        if ($user->guardian_id) {
            return Guardian::query()->find($user->guardian_id);
        }

        $byExact = Guardian::query()->where('phone', $user->phone)->first();
        if ($byExact) {
            return $byExact;
        }

        $national = self::iraqiNational10FromE164($user->phone);
        if ($national !== null) {
            return Guardian::query()->where('phone', $national)->first();
        }

        return null;
    }

    private static function iraqiNational10FromE164(string $phone): ?string
    {
        if (str_starts_with($phone, '964') && strlen($phone) === 13) {
            return substr($phone, 3);
        }

        return null;
    }

    /** @return list<int> */
    public static function studentIdsFor(User $user): array
    {
        $guardian = self::guardian($user);
        if (! $guardian) {
            return [];
        }

        return Student::query()->where('guardian_id', $guardian->id)->pluck('id')->all();
    }

    /** @return Collection<int, Student> */
    public static function studentsFor(User $user): Collection
    {
        $ids = self::studentIdsFor($user);
        if ($ids === []) {
            return collect();
        }

        return Student::query()->whereIn('id', $ids)->get();
    }

    public static function ownsStudent(User $user, int $studentId): bool
    {
        return in_array($studentId, self::studentIdsFor($user), true);
    }
}
