<?php

namespace App\Services\IdCard;

use App\Enums\PhoneAccountType;

final class IdCardOccupancy
{
    public function __construct(
        public string $entity,
        public int $entityId,
        public PhoneAccountType $accountType,
    ) {}

    public function isSameRecordAs(IdCardRecordIdentity $identity): bool
    {
        if ($this->entity === 'guardian') {
            return $identity->guardianId !== null && $identity->guardianId === $this->entityId;
        }

        if ($this->entity === 'driver') {
            return $identity->driverId !== null && $identity->driverId === $this->entityId;
        }

        return false;
    }
}
