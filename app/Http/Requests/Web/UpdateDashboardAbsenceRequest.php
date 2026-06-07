<?php

namespace App\Http\Requests\Web;

use App\Enums\AbsenceReason;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDashboardAbsenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    /** @return array<string, list<string|ValidationRule>> */
    public function rules(): array
    {
        return [
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date'],
            'reason' => ['sometimes', 'string', Rule::in(array_column(AbsenceReason::cases(), 'value'))],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
