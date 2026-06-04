<?php

namespace App\Observers;

use App\Models\Driver;
use App\Models\Guardian;
use App\Services\IdCard\DashboardIdCardValidator;
use App\Support\IdCardNumber;

final class ValidatesUniqueIdCardsObserver
{
    public function __construct(
        private readonly DashboardIdCardValidator $validator,
    ) {}

    public function saving(Driver|Guardian $model): void
    {
        if ($model->isDirty('id_card_number')) {
            $model->id_card_number = IdCardNumber::normalize($model->id_card_number);
        }

        match (true) {
            $model instanceof Driver => $this->validator->validateDriver($model),
            $model instanceof Guardian => $this->validator->validateGuardian($model),
            default => null,
        };
    }
}
