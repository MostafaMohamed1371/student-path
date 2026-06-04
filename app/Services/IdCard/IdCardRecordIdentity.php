<?php

namespace App\Services\IdCard;

final class IdCardRecordIdentity
{
    public function __construct(
        public ?int $guardianId = null,
        public ?int $driverId = null,
        public ?int $guardianSchoolId = null,
    ) {}
}
