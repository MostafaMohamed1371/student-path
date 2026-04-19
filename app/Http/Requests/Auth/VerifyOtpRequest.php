<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\Concerns\PreparesIraqPhoneInput;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class VerifyOtpRequest extends FormRequest
{
    use PreparesIraqPhoneInput;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string|ValidationRule>>
     */
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'size:10', 'regex:/^[1-9]\d{9}$/'],
            'code' => ['required', 'string', 'regex:/^\d{4}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.size' => 'Enter exactly 10 digits for your mobile number (country code 964 is added automatically).',
            'phone.regex' => 'The mobile number must be 10 digits and cannot start with 0.',
        ];
    }
}
