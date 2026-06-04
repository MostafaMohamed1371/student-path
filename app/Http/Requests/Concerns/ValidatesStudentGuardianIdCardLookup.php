<?php

namespace App\Http\Requests\Concerns;

use App\Models\Guardian;
use App\Support\IdCardNumber;
use Illuminate\Validation\Validator;

trait ValidatesStudentGuardianIdCardLookup
{
    protected function assertStudentGuardianIdCardLookup(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->hasAny(['guardian_id', 'guardian_id_card_number'])) {
                return;
            }

            $lookup = IdCardNumber::normalize($this->input('guardian_id_card_number'));
            if ($lookup === null) {
                return;
            }

            $guardian = Guardian::query()->find((int) $this->input('guardian_id'));
            if ($guardian === null) {
                return;
            }

            $stored = IdCardNumber::normalize($guardian->id_card_number);
            if ($stored !== null && $stored !== $lookup) {
                $validator->errors()->add(
                    'guardian_id_card_number',
                    __('dashboard.student_guardian_id_card_mismatch'),
                );
            }
        });
    }
}
