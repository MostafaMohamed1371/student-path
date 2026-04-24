<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateGuardianRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string|ValidationRule>> */
    public function rules(): array
    {
        return [
            'schoolId' => ['sometimes', 'integer', 'exists:schools,id'],
            'fullName' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'string', 'size:10', 'regex:/^[1-9]\d{9}$/'],
            'backupPhone' => ['sometimes', 'nullable', 'string', 'size:10', 'regex:/^[1-9]\d{9}$/'],
            'idCardNumber' => ['sometimes', 'nullable', 'string', 'max:64'],
            'status' => ['sometimes', 'in:active,inactive'],
        ];
    }
}
