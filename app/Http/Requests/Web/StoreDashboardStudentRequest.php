<?php

namespace App\Http\Requests\Web;

use App\Enums\PhoneAccountType;
use App\Http\Requests\Concerns\AppendsGuardianFamilySuffixToStudentName;
use App\Http\Requests\Concerns\SyncsAgeFromDateOfBirth;
use App\Http\Requests\Concerns\ValidatesDashboardIraqLocation;
use App\Http\Requests\Concerns\ValidatesStudentGuardianIdCardLookup;
use App\Http\Requests\Concerns\ValidatesUniqueDashboardPhone;
use App\Support\IdCardNumber;
use App\Rules\FullNameWordCount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreDashboardStudentRequest extends FormRequest
{
    use AppendsGuardianFamilySuffixToStudentName;
    use SyncsAgeFromDateOfBirth;
    use ValidatesDashboardIraqLocation;
    use ValidatesStudentGuardianIdCardLookup;
    use ValidatesUniqueDashboardPhone;
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('student_phone')) {
            $this->merge([
                'student_phone' => preg_replace('/\D+/', '', (string) $this->input('student_phone')),
            ]);
        }

        $this->syncAgeFromDateOfBirth();
        $this->appendGuardianFamilySuffixToStudentFullName();

        if ($this->has('guardian_id_card_number')) {
            $this->merge([
                'guardian_id_card_number' => IdCardNumber::normalize($this->input('guardian_id_card_number')),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'school_id' => ['required', 'integer', 'exists:schools,id'],
            'full_name' => ['required', 'string', 'max:255', new FullNameWordCount(3, 4)],
            'gender' => ['required', 'in:male,female'],
            'date_of_birth' => ['nullable', 'date'],
            'age' => ['nullable', 'integer', 'min:3', 'max:30'],
            'profile_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:1536'],
            'grade' => ['required', 'string', 'max:100'],
            'student_phone' => ['required', 'regex:/^[1-9][0-9]{9}$/'],
            'guardian_id' => ['required', 'integer', 'exists:guardians,id'],
            'guardian_id_card_number' => ['nullable', 'string', 'max:64'],
            'relationship' => ['required', 'string', 'max:100'],
            ...$this->dashboardIraqLocationRules(),
            'nearest_landmark' => ['required', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'status' => ['required', 'in:active,inactive'],
            'shift_period' => ['nullable', 'in:MORNING,EVENING'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $this->assertUniqueDashboardPhone($validator, 'student_phone', PhoneAccountType::Student);
        $this->assertStudentGuardianIdCardLookup($validator);
        $this->assertStudentFirstNameIsSingleWordBeforeAppend($validator);
    }

    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);

        if ($key !== null) {
            return $validated;
        }

        return $this->mergeResolvedIraqLocationAttributes($validated);
    }
}
