<?php

namespace App\Http\Requests\Web;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDashboardStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'school_id' => ['required', 'integer', 'exists:schools,id'],
            'full_name' => ['required', 'string', 'max:255'],
            'gender' => ['required', 'in:male,female'],
            'date_of_birth' => ['nullable', 'date'],
            'age' => ['nullable', 'integer', 'min:3', 'max:30'],
            'profile_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:1536'],
            'grade' => ['required', 'string', 'max:100'],
            'student_phone' => ['required', 'regex:/^[1-9][0-9]{9}$/'],
            'guardian_id' => ['required', 'integer', 'exists:guardians,id'],
            'relationship' => ['required', 'string', 'max:100'],
            'district_area' => ['required', 'string', 'max:255'],
            'nearest_landmark' => ['required', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'status' => ['required', 'in:active,inactive'],
        ];
    }
}
