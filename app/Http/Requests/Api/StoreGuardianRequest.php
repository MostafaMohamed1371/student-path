<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreGuardianRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string|ValidationRule>> */
    public function rules(): array
    {
        return [
            'schoolId' => ['required', 'integer', 'exists:schools,id'],
            'fullName' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'size:10', 'regex:/^[1-9]\d{9}$/'],
            'backupPhone' => ['nullable', 'string', 'size:10', 'regex:/^[1-9]\d{9}$/'],
            'idCardNumber' => ['nullable', 'string', 'max:64'],
            'status' => ['required', 'in:active,inactive'],
        ];
    }
}
