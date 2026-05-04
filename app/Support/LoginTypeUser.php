<?php

namespace App\Support;

use App\Models\Student;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Login channel for OTP (guardian, student, driver); same rules as {@see OtpService}.
 */
final class LoginTypeUser
{
    public static function matches(string $typeUser, User $user): bool
    {
        return match ($typeUser) {
            'guardian' => ParentContext::guardian($user) !== null,
            'student' => self::userIsStudentAccount($user),
            'driver' => $user->driver()->exists(),
            default => false,
        };
    }

    /**
     * @throws ValidationException
     */
    public static function assertMatches(string $typeUser, User $user): void
    {
        if (! self::matches($typeUser, $user)) {
            throw ValidationException::withMessages([
                'type_user' => ['This login type does not match this phone number.'],
            ]);
        }
    }

    /**
     * For `/api/auth/me` when the client did not send `type_user`.
     * First match wins: driver, guardian, student.
     */
    public static function resolve(User $user): ?string
    {
        foreach (['driver', 'guardian', 'student'] as $type) {
            if (self::matches($type, $user)) {
                return $type;
            }
        }

        return null;
    }

    private static function userIsStudentAccount(User $user): bool
    {
        $national = str_starts_with($user->phone, '964') && strlen($user->phone) === 13
            ? substr($user->phone, 3)
            : null;

        if ($national !== null) {
            $byNational = Student::query()
                ->where(function ($q) use ($user, $national): void {
                    $q->where('student_phone', $national)->orWhere('student_phone', $user->phone);
                })
                ->exists();
            if ($byNational) {
                return true;
            }
        }

        return Student::query()
            ->where('student_phone', $user->phone)
            ->exists();
    }
}
