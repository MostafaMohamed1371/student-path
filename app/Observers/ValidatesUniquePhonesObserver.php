<?php

namespace App\Observers;

use App\Models\Driver;
use App\Models\Guardian;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Services\Phone\DashboardPhoneValidator;

final class ValidatesUniquePhonesObserver
{
    public function __construct(
        private readonly DashboardPhoneValidator $validator,
    ) {}

    public function saving(School|Driver|Student|Guardian|User $model): void
    {
        match (true) {
            $model instanceof School => $this->validator->validateSchool($model),
            $model instanceof Driver => $this->validator->validateDriver($model),
            $model instanceof Student => $this->validator->validateStudent($model),
            $model instanceof Guardian => $this->validator->validateGuardian($model),
            $model instanceof User => $this->validator->validateUser($model),
            default => null,
        };
    }
}
