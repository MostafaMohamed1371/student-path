<?php

namespace App\Services\Phone;

use App\Enums\PhoneAccountType;
use App\Models\Driver;
use App\Models\Guardian;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
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

    private function matchesStoredPhone(mixed $stored, string $national, string $canonical): bool
    {
        if (! is_string($stored) || trim($stored) === '') {
            return false;
        }

        $digits = $this->nationalDigits($stored);

        return $digits === $national
            || $stored === $national
            || $stored === $canonical
            || $digits === $this->nationalDigits($canonical);
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
                || $this->isCompanionUserForExcept($occupancy, $except, $intendedType)
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

            return $user->phone === $this->canonical((string) $student->student_phone)
                && ($user->phone_account_type === null
                    || $user->phone_account_type === PhoneAccountType::Student->value);
        }

        if ($intendedType === PhoneAccountType::Guardian && $except->guardianId !== null) {
            return (int) $user->guardian_id === $except->guardianId;
        }

        return false;
    }
}
