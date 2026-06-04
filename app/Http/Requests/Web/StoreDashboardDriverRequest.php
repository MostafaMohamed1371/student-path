<?php

namespace App\Http\Requests\Web;

use App\Enums\PhoneAccountType;
use App\Http\Requests\Concerns\ValidatesUniqueDashboardIdCard;
use App\Http\Requests\Concerns\ValidatesUniqueDashboardPhone;
use App\Support\IdCardNumber;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreDashboardDriverRequest extends FormRequest
{
    use ValidatesUniqueDashboardIdCard;
    use ValidatesUniqueDashboardPhone;
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['primary_phone', 'emergency_phone'] as $field) {
            if ($this->has($field)) {
                $this->merge([
                    $field => preg_replace('/\D+/', '', (string) $this->input($field)),
                ]);
            }
        }

        foreach (['rating_avg', 'rating_count'] as $field) {
            if ($this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }

        if ($this->has('id_card_number')) {
            $this->merge([
                'id_card_number' => IdCardNumber::normalize($this->input('id_card_number')),
            ]);
        }
    }

    /** @return array<string, list<string|ValidationRule>> */
    public function rules(): array
    {
        return [
            'school_id' => ['required', 'integer', 'exists:schools,id'],
            'first_name' => ['required', 'string', 'max:255'],
            'father_name' => ['required', 'string', 'max:255'],
            'grandfather_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'age' => ['required', 'integer', 'min:18', 'max:80'],
            'id_card_number' => ['required', 'string', 'max:255'],
            'license_number' => ['required', 'string', 'max:255'],
            'primary_phone' => ['required', 'string', 'size:10', 'regex:/^[1-9]\d{9}$/'],
            'emergency_phone' => ['required', 'string', 'size:10', 'regex:/^[1-9]\d{9}$/'],
            'residential_address' => ['required', 'string', 'max:255'],
            'status' => ['required', 'in:active,inactive'],
            'profile_image' => ['nullable', 'file', 'image', 'max:4096'],
            'id_card_image' => ['nullable', 'file', 'image', 'max:4096'],
            'license_image' => ['nullable', 'file', 'image', 'max:4096'],
            'non_conviction_certificate' => ['nullable', 'file', 'max:4096'],
            'rating_avg' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'rating_count' => ['nullable', 'integer', 'min:0', 'max:999999'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $this->assertUniqueDashboardPhone($validator, 'primary_phone', PhoneAccountType::Driver);
        $this->assertUniqueDashboardPhone($validator, 'emergency_phone', PhoneAccountType::Driver);
        $this->assertUniqueDashboardIdCard($validator, 'id_card_number');
    }
}
