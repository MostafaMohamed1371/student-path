<?php

namespace App\Services\Phone;

use App\Enums\PhoneAccountType;
use App\Models\Driver;
use App\Models\Guardian;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Support\IdCardNumber;

/**
 * Validates dashboard phone fields on create/update (used by form requests and model observers).
 */
final class DashboardPhoneValidator
{
    public function __construct(
        private readonly DashboardPhoneRegistry $registry,
        private readonly PhoneNormalizer $phoneNormalizer,
    ) {}

    public function validateSchool(School $school): void
    {
        $schoolId = $school->exists ? (int) $school->getKey() : null;

        if ($this->shouldCheck($school, 'admin_phone')) {
            $this->registry->assertAvailable(
                (string) $school->admin_phone,
                PhoneAccountType::School,
                new PhoneRecordIdentity(schoolId: $schoolId, schoolPhoneField: 'admin_phone'),
                'admin_phone',
            );
        }

        if ($this->shouldCheck($school, 'authorized_person_phone')) {
            $this->registry->assertAvailable(
                (string) $school->authorized_person_phone,
                PhoneAccountType::School,
                new PhoneRecordIdentity(schoolId: $schoolId, schoolPhoneField: 'authorized_person_phone'),
                'authorized_person_phone',
            );
        }
    }

    public function validateDriver(Driver $driver): void
    {
        $driverId = $driver->exists ? (int) $driver->getKey() : null;

        $driverUserId = $driver->user_id ? (int) $driver->user_id : null;

        if ($this->shouldCheck($driver, 'primary_phone')) {
            $this->registry->assertAvailable(
                (string) $driver->primary_phone,
                PhoneAccountType::Driver,
                new PhoneRecordIdentity(
                    driverId: $driverId,
                    driverUserId: $driverUserId,
                    driverPhoneField: 'primary_phone',
                ),
                'primary_phone',
            );
        }

        if ($this->shouldCheck($driver, 'emergency_phone')) {
            $this->registry->assertAvailable(
                (string) $driver->emergency_phone,
                PhoneAccountType::Driver,
                new PhoneRecordIdentity(
                    driverId: $driverId,
                    driverUserId: $driverUserId,
                    driverPhoneField: 'emergency_phone',
                ),
                'emergency_phone',
            );
        }
    }

    public function validateStudent(Student $student): void
    {
        if (! $this->shouldCheck($student, 'student_phone')) {
            return;
        }

        $studentId = $student->exists ? (int) $student->getKey() : null;

        $this->registry->assertAvailable(
            (string) $student->student_phone,
            PhoneAccountType::Student,
            new PhoneRecordIdentity(studentId: $studentId),
            'student_phone',
        );
    }

    public function validateGuardian(Guardian $guardian): void
    {
        $guardianId = $guardian->exists ? (int) $guardian->getKey() : null;
        $schoolId = (int) $guardian->school_id;
        $idCard = IdCardNumber::normalize($guardian->id_card_number);

        if ($this->shouldCheck($guardian, 'phone')) {
            $this->registry->assertAvailable(
                (string) $guardian->phone,
                PhoneAccountType::Guardian,
                new PhoneRecordIdentity(
                    guardianId: $guardianId,
                    guardianPhoneField: 'phone',
                    guardianSchoolId: $schoolId,
                    guardianIdCardNumber: $idCard,
                ),
                'phone',
            );
        }

        if ($this->shouldCheck($guardian, 'backup_phone')) {
            $this->registry->assertAvailable(
                (string) $guardian->backup_phone,
                PhoneAccountType::Guardian,
                new PhoneRecordIdentity(
                    guardianId: $guardianId,
                    guardianPhoneField: 'backup_phone',
                    guardianSchoolId: $schoolId,
                    guardianIdCardNumber: $idCard,
                ),
                'backup_phone',
            );
        }
    }

    public function validateUser(User $user): void
    {
        if (! $this->shouldCheck($user, 'phone')) {
            return;
        }

        $national = $this->registry->nationalDigits((string) $user->phone);
        if (! $this->phoneNormalizer->isValidIraqiMobile($national)) {
            return;
        }

        $type = $user->is_admin ? PhoneAccountType::Admin : PhoneAccountType::School;
        if ($user->driver()->exists()) {
            $type = PhoneAccountType::Driver;
        } elseif ($user->guardian_id !== null) {
            $type = PhoneAccountType::Guardian;
        } elseif ($user->phone_account_type === PhoneAccountType::Student->value) {
            $type = PhoneAccountType::Student;
        } elseif ($user->phone_account_type === PhoneAccountType::School->value) {
            $type = PhoneAccountType::School;
        }

        $userId = $user->exists ? (int) $user->getKey() : null;

        $this->registry->assertAvailable(
            $national,
            $type,
            new PhoneRecordIdentity(userId: $userId),
            'phone',
        );
    }

    /**
     * @param  School|Driver|Student|Guardian|User  $model
     */
    private function shouldCheck(object $model, string $attribute): bool
    {
        if (! $model->isDirty($attribute)) {
            return false;
        }

        $value = $model->getAttribute($attribute);

        return is_string($value) && trim($value) !== '';
    }
}
