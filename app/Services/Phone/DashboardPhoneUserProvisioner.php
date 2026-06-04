<?php

namespace App\Services\Phone;

use App\Enums\PhoneAccountType;
use App\Models\Guardian;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class DashboardPhoneUserProvisioner
{
    public function __construct(
        private readonly PhoneNormalizer $phoneNormalizer,
        private readonly DashboardPhoneRegistry $registry,
    ) {}

    public function upsertSchoolStaff(School $school, string $nationalPhone, string $name): User
    {
        $phone = $this->phoneNormalizer->normalize($nationalPhone);

        return User::query()->updateOrCreate(
            ['phone' => $phone],
            [
                'name' => $name,
                'school_id' => $school->id,
                'guardian_id' => null,
                'phone_account_type' => PhoneAccountType::School->value,
                'password' => config('dashboard.seed_password'),
                'is_active' => $school->status === 'active',
                'is_admin' => false,
                'phone_verified_at' => now(),
            ],
        );
    }

    public function upsertDriver(
        string $nationalPhone,
        string $name,
        bool $isActive = true,
        string $errorAttribute = 'primary_phone',
    ): User {
        $phone = $this->phoneNormalizer->normalize($nationalPhone);
        $existing = User::query()->where('phone', $phone)->first();

        if ($existing !== null) {
            $type = $this->registry->accountTypeForUser($existing);
            if ($type !== PhoneAccountType::Driver) {
                throw ValidationException::withMessages([
                    $errorAttribute => [
                        __('dashboard.phone_already_used', ['owner' => $type->label()]),
                    ],
                ]);
            }
        }

        return User::query()->updateOrCreate(
            ['phone' => $phone],
            [
                'name' => $name !== '' ? $name : null,
                'phone_account_type' => PhoneAccountType::Driver->value,
                'school_id' => null,
                'guardian_id' => null,
                'password' => config('dashboard.seed_password'),
                'is_active' => $isActive,
                'is_admin' => false,
                'phone_verified_at' => now(),
            ],
        );
    }

    public function upsertGuardian(Guardian $guardian, string $nationalPhone, string $name): User
    {
        if (! $this->phoneNormalizer->isValidIraqiMobile($nationalPhone)) {
            throw ValidationException::withMessages([
                'phone' => [__('validation.regex', ['attribute' => __('dashboard.phone')])],
            ]);
        }

        $phone = $this->phoneNormalizer->normalize($nationalPhone);

        return User::query()->updateOrCreate(
            ['phone' => $phone],
            [
                'name' => $name,
                'school_id' => $guardian->school_id,
                'guardian_id' => $guardian->id,
                'phone_account_type' => PhoneAccountType::Guardian->value,
                'password' => config('dashboard.seed_password'),
                'is_active' => $guardian->status === 'active',
                'is_admin' => false,
                'phone_verified_at' => now(),
            ],
        );
    }

    public function upsertStudent(Student $student, string $nationalPhone, string $name): User
    {
        if (! $this->phoneNormalizer->isValidIraqiMobile($nationalPhone)) {
            throw ValidationException::withMessages([
                'student_phone' => [__('validation.regex', ['attribute' => __('dashboard.phone')])],
            ]);
        }

        $phone = $this->phoneNormalizer->normalize($nationalPhone);
        $existing = User::query()->where('phone', $phone)->first();

        if ($existing !== null) {
            $type = $this->registry->accountTypeForUser($existing);
            if ($type !== PhoneAccountType::Student) {
                throw ValidationException::withMessages([
                    'student_phone' => [
                        __('dashboard.phone_already_used', ['owner' => $type->label()]),
                    ],
                ]);
            }
        }

        return User::query()->updateOrCreate(
            ['phone' => $phone],
            [
                'name' => $name,
                'school_id' => $student->school_id,
                'guardian_id' => null,
                'phone_account_type' => PhoneAccountType::Student->value,
                'password' => config('dashboard.seed_password'),
                'is_active' => $student->status === 'active',
                'is_admin' => false,
                'phone_verified_at' => now(),
            ],
        );
    }
}
