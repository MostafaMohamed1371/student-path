<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDriverRequest extends FormRequest
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
            'firstName' => ['sometimes', 'string', 'max:255'],
            'fatherName' => ['sometimes', 'string', 'max:255'],
            'grandfatherName' => ['sometimes', 'string', 'max:255'],
            'lastName' => ['sometimes', 'string', 'max:255'],
            'age' => ['sometimes', 'integer', 'min:18', 'max:80'],
            'idCardNumber' => ['sometimes', 'string', 'max:255'],
            'licenseNumber' => ['sometimes', 'string', 'max:255'],
            'primaryPhone' => ['sometimes', 'string', 'size:10', 'regex:/^[1-9]\d{9}$/'],
            'emergencyPhone' => ['sometimes', 'string', 'max:20'],
            'residentialAddress' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', 'in:active,inactive'],
            'idCardImage' => ['sometimes', 'nullable', 'file', 'image', 'max:4096'],
            'licenseImage' => ['sometimes', 'nullable', 'file', 'image', 'max:4096'],
            'nonConvictionCertificate' => ['sometimes', 'nullable', 'file', 'max:4096'],
        ];
    }
}
