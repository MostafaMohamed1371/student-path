<?php

namespace App\Http\Requests\Api;

use App\Enums\PhoneAccountType;
use App\Http\Requests\Concerns\ValidatesUniqueDashboardPhone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreDriverRequest extends FormRequest
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
            'firstName' => ['required', 'string', 'max:255'],
            'fatherName' => ['required', 'string', 'max:255'],
            'grandfatherName' => ['required', 'string', 'max:255'],
            'lastName' => ['required', 'string', 'max:255'],
            'age' => ['required', 'integer', 'min:18', 'max:80'],
            'idCardNumber' => ['required', 'string', 'max:255'],
            'licenseNumber' => ['required', 'string', 'max:255'],
            'primaryPhone' => ['required', 'string', 'size:10', 'regex:/^[1-9]\d{9}$/'],
            'emergencyPhone' => ['required', 'string', 'size:10', 'regex:/^[1-9]\d{9}$/'],
            'residentialAddress' => ['required', 'string', 'max:255'],
            'status' => ['required', 'in:active,inactive'],
            'monthlySubscriptionPrice' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
            'idCardImage' => ['nullable', 'file', 'image', 'max:4096'],
            'licenseImage' => ['nullable', 'file', 'image', 'max:4096'],
            'nonConvictionCertificate' => ['nullable', 'file', 'max:4096'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $this->assertUniqueDashboardPhone($validator, 'primaryPhone', PhoneAccountType::Driver);
        $this->assertUniqueDashboardPhone($validator, 'emergencyPhone', PhoneAccountType::Driver);
    }
}
