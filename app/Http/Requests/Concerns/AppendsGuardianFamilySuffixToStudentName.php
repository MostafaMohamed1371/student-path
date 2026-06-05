<?php

namespace App\Http\Requests\Concerns;

use App\Models\Guardian;
use App\Support\StudentNameComposer;
use Illuminate\Validation\Validator;

trait AppendsGuardianFamilySuffixToStudentName
{
    protected function appendGuardianFamilySuffixToStudentFullName(): void
    {
        $this->merge([
            '_student_full_name_raw' => trim((string) $this->input('full_name', '')),
        ]);

        if (! $this->filled('full_name') || ! $this->filled('guardian_id')) {
            return;
        }

        $words = preg_split('/\s+/u', trim((string) $this->input('full_name')), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($words) !== 1) {
            return;
        }

        $guardian = Guardian::query()->find((int) $this->input('guardian_id'));
        if (! $guardian instanceof Guardian) {
            return;
        }

        $this->merge([
            'full_name' => StudentNameComposer::composeWithGuardianParentName(
                (string) $this->input('full_name'),
                (string) $guardian->full_name,
            ),
        ]);
    }

    protected function assertStudentFirstNameIsSingleWordBeforeAppend(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $raw = trim((string) $this->input('_student_full_name_raw', ''));
            $words = preg_split('/\s+/u', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];

            if (count($words) === 2) {
                $validator->errors()->add(
                    'full_name',
                    (string) __('dashboard.student_first_name_single_word'),
                );
            }
        });
    }
}
