<?php

namespace App\Services\IdCard;

use App\Models\Driver;
use App\Models\Guardian;
use App\Support\IdCardNumber;
use Illuminate\Validation\ValidationException;

final class DashboardIdCardValidator
{
    public function __construct(
        private readonly DashboardIdCardRegistry $registry,
    ) {}

    public function validateGuardian(Guardian $guardian): void
    {
        if (! $this->shouldCheck($guardian, 'id_card_number')) {
            return;
        }

        $this->registry->assertAvailable(
            (string) $guardian->id_card_number,
            new IdCardRecordIdentity(
                guardianId: $guardian->exists ? (int) $guardian->getKey() : null,
                guardianSchoolId: (int) $guardian->school_id,
            ),
            'id_card_number',
        );
    }

    public function validateDriver(Driver $driver): void
    {
        if (! $this->shouldCheck($driver, 'id_card_number')) {
            return;
        }

        $this->registry->assertAvailable(
            (string) $driver->id_card_number,
            new IdCardRecordIdentity(driverId: $driver->exists ? (int) $driver->getKey() : null),
            'id_card_number',
        );
    }

    /**
     * @param  Guardian|Driver  $model
     */
    private function shouldCheck($model, string $field): bool
    {
        if (! $model->isDirty($field)) {
            return false;
        }

        return IdCardNumber::normalize($model->{$field}) !== null;
    }
}
