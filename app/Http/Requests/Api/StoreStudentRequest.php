<?php

namespace App\Http\Requests\Api;

use App\Enums\PhoneAccountType;
use App\Http\Requests\Concerns\ValidatesUniqueDashboardPhone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreStudentRequest extends FormRequest
{
    use ValidatesUniqueDashboardPhone;
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string|ValidationRule>> */
    public function rules(): array
    {
        return [
            'schoolId' => ['required', 'integer', 'exists:schools,id'],
            'guardianId' => ['required', 'integer', 'exists:guardians,id'],
            'fullName' => ['required', 'string', 'max:255'],
            'gender' => ['required', 'in:male,female'],
            'dateOfBirth' => ['nullable', 'date'],
            'age' => ['nullable', 'integer', 'min:3', 'max:30'],
            'profilePhoto' => ['nullable', 'file', 'image', 'max:4096'],
            'grade' => ['required', 'string', 'max:100'],
            'studentPhone' => ['required', 'string', 'size:10', 'regex:/^[1-9]\d{9}$/'],
            'relationship' => ['required', 'string', 'max:100'],
            'districtArea' => ['required', 'string', 'max:255'],
            'nearestLandmark' => ['required', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'status' => ['required', 'in:active,inactive'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $this->assertUniqueDashboardPhone($validator, 'studentPhone', PhoneAccountType::Student);
    }
}
