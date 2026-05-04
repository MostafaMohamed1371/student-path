<?php

namespace App\Http\Requests\Web;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreDashboardTripRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    /** @return array<string, list<string|ValidationRule>> */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'trip_history_id' => ['nullable', 'integer', 'exists:trip_histories,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
