<?php

namespace App\Services\Phone;

use App\Enums\PhoneAccountType;
use App\Models\Driver;
use App\Models\Guardian;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Support\IdCardNumber;
use Illuminate\Validation\ValidationException;

final class DashboardPhoneRegistry
{
    public function __construct(
        private readonly PhoneNormalizer $phoneNormalizer,
    ) {}

    public function nationalDigits(string $raw): string
    {
        return preg_replace('/\D+/', '', $raw) ?? '';
    }

    public function canonical(string $raw): string
    {
        return $this->phoneNormalizer->normalize($raw);
    }

    /**
     * @return list<PhoneOccupancy>
     */
    public function occupanciesFor(string $raw): array
    {
        $national = $this->nationalDigits($raw);
        if (! $this->phoneNormalizer->isValidIraqiMobile($national)) {
            return [];
        }

        $canonical = $this->canonical($national);
        $phoneVariants = array_values(array_unique([$canonical, $national]));
        $occupancies = [];

        foreach (User::query()->whereIn('phone', $phoneVariants)->get() as $user) {
            $occupancies[] = new PhoneOccupancy(
                'user',
                (int) $user->id,
                'phone',
                $this->accountTypeForUser($user),
            );
        }

        foreach (School::query()
            ->where(function ($query) use ($phoneVariants): void {
                $query->whereIn('admin_phone', $phoneVariants)
                    ->orWhereIn('authorized_person_phone', $phoneVariants);
            })
            ->get() as $school) {
            if ($this->matchesStoredPhone($school->admin_phone, $national, $canonical)) {
                $occupancies[] = new PhoneOccupancy(
                    'school',
                    (int) $school->id,
                    'admin_phone',
                    PhoneAccountType::School,
                );
            }
            if ($this->matchesStoredPhone($school->authorized_person_phone, $national, $canonical)) {
                $occupancies[] = new PhoneOccupancy(
                    'school',
                    (int) $school->id,
                    'authorized_person_phone',
                    PhoneAccountType::School,
                );
            }
        }

        foreach (Driver::query()
            ->where(function ($query) use ($phoneVariants): void {
                $query->whereIn('primary_phone', $phoneVariants)
                    ->orWhereIn('emergency_phone', $phoneVariants);
            })
            ->get() as $driver) {
            if ($this->matchesStoredPhone($driver->primary_phone, $national, $canonical)) {
                $occupancies[] = new PhoneOccupancy(
                    'driver',
                    (int) $driver->id,
                    'primary_phone',
                    PhoneAccountType::Driver,
                );
            }
            if ($this->matchesStoredPhone($driver->emergency_phone, $national, $canonical)) {
                $occupancies[] = new PhoneOccupancy(
                    'driver',
                    (int) $driver->id,
                    'emergency_phone',
                    PhoneAccountType::Driver,
                );
            }
        }

        foreach (Student::query()->whereIn('student_phone', $phoneVariants)->get() as $student) {
            $occupancies[] = new PhoneOccupancy(
                'student',
                (int) $student->id,
                'student_phone',
                PhoneAccountType::Student,
            );
        }

        foreach (Guardian::query()
            ->where(function ($query) use ($phoneVariants): void {
                $query->whereIn('phone', $phoneVariants)
                    ->orWhereIn('backup_phone', $phoneVariants);
            })
            ->get() as $guardian) {
            if ($this->matchesStoredPhone($guardian->phone, $national, $canonical)) {
                $occupancies[] = new PhoneOccupancy(
                    'guardian',
                    (int) $guardian->id,
                    'phone',
                    PhoneAccountType::Guardian,
                );
            }
            if ($this->matchesStoredPhone($guardian->backup_phone, $national, $canonical)) {
                $occupancies[] = new PhoneOccupancy(
                    'guardian',
                    (int) $guardian->id,
                    'backup_phone',
                    PhoneAccountType::Guardian,
                );
            }
        }

        return $occupancies;
    }

    public function phonesMatch(mixed $left, mixed $right): bool
    {
        $leftCanonical = $this->canonical($this->nationalDigits((string) $left));
        $rightCanonical = $this->canonical($this->nationalDigits((string) $right));
        if ($leftCanonical === '' || $rightCanonical === '') {
            return false;
        }

        return $leftCanonical === $rightCanonical;
    }

    private function matchesStoredPhone(mixed $stored, string $national, string $canonical): bool
    {
        if (! is_string($stored) || trim($stored) === '') {
            return false;
        }

        return $this->phonesMatch($stored, $national);
    }

    /**
     * @throws ValidationException
     */
    public function assertAvailable(
        string $raw,
        PhoneAccountType $intendedType,
        ?PhoneRecordIdentity $except = null,
        string $errorAttribute = 'phone',
    ): void {
        $national = $this->nationalDigits($raw);
        if ($national === '') {
            return;
        }

        foreach ($this->occupanciesFor($national) as $occupancy) {
            if ($except !== null && (
                $occupancy->isSameRecordAs($except)
                || $this->isLinkedDriverOccupancyForUser($occupancy, $except)
                || $this->isCompanionUserForExcept($occupancy, $except, $intendedType)
                || $this->isGuardianPhoneAllowedAtAnotherSchool($occupancy, $except, $intendedType)
            )) {
                continue;
            }

            throw ValidationException::withMessages([
                $errorAttribute => [
                    __('dashboard.phone_already_used', [
                        'owner' => $occupancy->accountType->label(),
                    ]),
                ],
            ]);
        }
    }

    public function accountTypeForUser(User $user): PhoneAccountType
    {
        if ($user->is_admin) {
            return PhoneAccountType::Admin;
        }

        $stored = $user->phone_account_type;
        if (is_string($stored) && $stored !== '') {
            $type = PhoneAccountType::tryFrom($stored);
            if ($type !== null) {
                return $type;
            }
        }

        if ($user->driver()->exists()) {
            return PhoneAccountType::Driver;
        }

        if ($user->guardian_id !== null) {
            return PhoneAccountType::Guardian;
        }

        if ($user->school_id !== null) {
            return PhoneAccountType::School;
        }

        return PhoneAccountType::Student;
    }

    /**
     * Driver login users share their phone with the linked driver row(s).
     */
    private function isLinkedDriverOccupancyForUser(
        PhoneOccupancy $occupancy,
        PhoneRecordIdentity $except,
    ): bool {
        if ($occupancy->entity !== 'driver' || $except->userId === null) {
            return false;
        }

        if ($except->driverId !== null && $occupancy->entityId === $except->driverId) {
            return true;
        }

        return Driver::query()
            ->whereKey($occupancy->entityId)
            ->where('user_id', $except->userId)
            ->exists();
    }

    /**
     * Login user rows created from school/driver/student/guardian sync share the same phone.
     */
    private function isCompanionUserForExcept(
        PhoneOccupancy $occupancy,
        PhoneRecordIdentity $except,
        PhoneAccountType $intendedType,
    ): bool {
        if ($occupancy->entity !== 'user') {
            return false;
        }

        $user = User::query()->find($occupancy->entityId);
        if ($user === null) {
            return false;
        }

        if ($intendedType === PhoneAccountType::School && $except->schoolId !== null) {
            return (int) $user->school_id === $except->schoolId
                && ! $user->is_admin
                && ! $user->driver()->exists()
                && ($user->phone_account_type === null
                    || $user->phone_account_type === PhoneAccountType::School->value);
        }

        if ($intendedType === PhoneAccountType::Driver) {
            if ($except->driverUserId !== null && (int) $user->id === $except->driverUserId) {
                return true;
            }

            if ($except->driverId !== null) {
                return $user->driver()->whereKey($except->driverId)->exists();
            }
        }

        if ($intendedType === PhoneAccountType::Student && $except->studentId !== null) {
            $student = Student::query()->find($except->studentId);
            if ($student === null) {
                return false;
            }

            $isLinkedStudentUser = (int) $user->guardian_id === 0
                && ($user->phone_account_type === null
                    || $user->phone_account_type === PhoneAccountType::Student->value);

            return $isLinkedStudentUser
                && $this->phonesMatch($user->phone, (string) $student->student_phone);
        }

        if ($intendedType === PhoneAccountType::Guardian && $except->guardianId !== null) {
            if ((int) $user->guardian_id === $except->guardianId) {
                return true;
            }

            $guardian = Guardian::query()->find($except->guardianId);

            return $guardian !== null
                && $user->phone_account_type === PhoneAccountType::Guardian->value
                && $this->phonesMatch($user->phone, (string) $guardian->phone);
        }

        if ($except->userId !== null && (int) $user->id === $except->userId) {
            return true;
        }

        if ($intendedType === PhoneAccountType::Guardian
            && $except->guardianSchoolId !== null
            && $except->guardianIdCardNumber !== null
            && $user->phone_account_type === PhoneAccountType::Guardian->value) {
            $linked = $this->guardianLinkedToUser($user);

            return $linked !== null
                && IdCardNumber::normalize($linked->id_card_number) === $except->guardianIdCardNumber
                && (int) $linked->school_id !== $except->guardianSchoolId;
        }

        return false;
    }

    private function isGuardianPhoneAllowedAtAnotherSchool(
        PhoneOccupancy $occupancy,
        PhoneRecordIdentity $except,
        PhoneAccountType $intendedType,
    ): bool {
        if ($intendedType !== PhoneAccountType::Guardian
            || $except->guardianSchoolId === null
            || $except->guardianIdCardNumber === null) {
            return false;
        }

        if ($occupancy->entity === 'guardian' && $occupancy->accountType === PhoneAccountType::Guardian) {
            $other = Guardian::query()->find($occupancy->entityId);
            if ($other === null) {
                return false;
            }

            return (int) $other->school_id !== $except->guardianSchoolId
                && IdCardNumber::normalize($other->id_card_number) === $except->guardianIdCardNumber;
        }

        return false;
    }

    private function guardianLinkedToUser(User $user): ?Guardian
    {
        if ($user->guardian_id !== null) {
            return Guardian::query()->find((int) $user->guardian_id);
        }

        $national = $this->nationalDigits((string) $user->phone);

        return Guardian::query()
            ->where(function ($query) use ($user, $national): void {
                $query->where('phone', $national)
                    ->orWhere('phone', $user->phone);
            })
            ->orderBy('id')
            ->first();
    }
}
