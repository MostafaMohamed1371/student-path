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

    /**
     * One owned student per school (lowest id) that has latitude/longitude, for transport distance
     * when the client does not pass `student_id`.
     *
     * @param  list<int>  $schoolIds
     * @return array<int, Student> keyed by school_id
     */
    public static function representativeStudentsWithLocationBySchool(User $user, array $schoolIds): array
    {
        $schoolIds = array_values(array_unique(array_filter(array_map('intval', $schoolIds), fn (int $id): bool => $id > 0)));
        if ($schoolIds === []) {
            return [];
        }

        $ownedIds = self::studentIdsFor($user);
        if ($ownedIds === []) {
            return [];
        }

        $candidates = Student::query()
            ->whereIn('id', $ownedIds)
            ->whereIn('school_id', $schoolIds)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderBy('school_id')
            ->orderBy('id')
            ->get();

        $out = [];
        foreach ($candidates as $student) {
            $sid = (int) $student->school_id;
            if (! isset($out[$sid])) {
                $out[$sid] = $student;
            }
        }

        return $out;
    }

    public static function ownsStudent(User $user, int $studentId): bool
    {
        return in_array($studentId, self::studentIdsFor($user), true);
    }

    /** Link parent user to the student's guardian and name when missing (e.g. after OTP login). */
    public static function ensureUserLinkedToStudent(User $user, Student $student): void
    {
        $student->loadMissing('guardian');
        $guardian = $student->guardian;
        if (! $guardian instanceof Guardian) {
            return;
        }

        $updates = [];
        if (! $user->guardian_id) {
            $updates['guardian_id'] = $guardian->id;
        }
        if ((! is_string($user->name) || trim($user->name) === '') && trim((string) $guardian->full_name) !== '') {
            $updates['name'] = trim($guardian->full_name);
        }
        if ($user->school_id === null && $guardian->school_id) {
            $updates['school_id'] = $guardian->school_id;
        }

        if ($updates !== []) {
            $user->forceFill($updates)->save();
        }
    }
}
