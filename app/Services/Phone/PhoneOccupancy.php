<?php

namespace App\Services\Phone;

use App\Enums\PhoneAccountType;

final readonly class PhoneOccupancy
{
    public function __construct(
        public string $entity,
        public int $entityId,
        public string $field,
        public PhoneAccountType $accountType,
    ) {}

    public function isSameRecordAs(PhoneRecordIdentity $identity): bool
    {
        if ($this->entity === 'school') {
            return $identity->schoolId === $this->entityId
                && $identity->schoolPhoneField === $this->field;
        }

        if ($this->entity === 'driver') {
            return $identity->driverId === $this->entityId
                && $identity->driverPhoneField === $this->field;
        }

        if ($this->entity === 'student') {
            return $identity->studentId === $this->entityId;
        }

        if ($this->entity === 'guardian') {
            return $identity->guardianId === $this->entityId
                && $identity->guardianPhoneField === $this->field;
        }

        if ($this->entity === 'user') {
            return $identity->userId === $this->entityId;
        }

        return false;
    }
}
