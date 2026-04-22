<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSchoolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string|ValidationRule>> */
    public function rules(): array
    {
        return [
            'schoolNameAr' => ['sometimes', 'string', 'max:255'],
            'schoolNameEn' => ['sometimes', 'string', 'max:255'],
            'province' => ['sometimes', 'string', 'max:255'],
            'district' => ['sometimes', 'string', 'max:255'],
            'address' => ['sometimes', 'string', 'max:255'],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'status' => ['sometimes', 'string', 'max:32'],
            'principalName' => ['sometimes', 'nullable', 'string', 'max:255'],
            'adminPhone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'authorizedPersonName' => ['sometimes', 'nullable', 'string', 'max:255'],
            'authorizedPersonPhone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'attachment' => ['sometimes', 'nullable', 'file', 'max:4096'],
        ];
    }
}
