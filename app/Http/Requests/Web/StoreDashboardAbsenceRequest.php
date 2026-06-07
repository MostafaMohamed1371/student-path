<?php

namespace App\Http\Requests\Web;

use App\Enums\AbsenceReason;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDashboardAbsenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    /** @return array<string, list<string|ValidationRule>> */
    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', Rule::in(array_column(AbsenceReason::cases(), 'value'))],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
