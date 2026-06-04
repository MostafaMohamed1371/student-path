<?php

namespace App\Services\Phone;

/**
 * Identifies an existing dashboard record when updating phones (same record + field may keep its number).
 */
final readonly class PhoneRecordIdentity
{
    public function __construct(
        public ?int $schoolId = null,
        public ?string $schoolPhoneField = null,
        public ?int $driverId = null,
        public ?int $driverUserId = null,
        public ?string $driverPhoneField = null,
        public ?int $studentId = null,
        public ?int $guardianId = null,
        public ?string $guardianPhoneField = null,
        public ?int $guardianSchoolId = null,
        public ?string $guardianIdCardNumber = null,
        public ?int $userId = null,
    ) {}
}
