<?php

namespace App\Http\Requests\Api;

use App\Enums\PhoneAccountType;
use App\Http\Requests\Concerns\ValidatesUniqueDashboardPhone;
use App\Models\Student;
use App\Services\Phone\PhoneRecordIdentity;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateStudentRequest extends FormRequest
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
            'schoolId' => ['sometimes', 'integer', 'exists:schools,id'],
            'guardianId' => ['sometimes', 'integer', 'exists:guardians,id'],
            'fullName' => ['sometimes', 'string', 'max:255'],
            'gender' => ['sometimes', 'in:male,female'],
            'dateOfBirth' => ['sometimes', 'nullable', 'date'],
            'age' => ['sometimes', 'nullable', 'integer', 'min:3', 'max:30'],
            'profilePhoto' => ['sometimes', 'nullable', 'file', 'image', 'max:4096'],
            'grade' => ['sometimes', 'string', 'max:100'],
            'studentPhone' => ['sometimes', 'string', 'size:10', 'regex:/^[1-9]\d{9}$/'],
            'relationship' => ['sometimes', 'string', 'max:100'],
            'districtArea' => ['sometimes', 'string', 'max:255'],
            'nearestLandmark' => ['sometimes', 'string', 'max:255'],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'status' => ['sometimes', 'in:active,inactive'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        if (! $this->filled('studentPhone')) {
            return;
        }

        /** @var Student $student */
        $student = $this->route('student');

        $this->assertUniqueDashboardPhone(
            $validator,
            'studentPhone',
            PhoneAccountType::Student,
            new PhoneRecordIdentity(studentId: (int) $student->id),
        );
    }
}
