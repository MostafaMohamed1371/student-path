<?php

namespace App\Http\Requests\Api;

use App\Enums\PhoneAccountType;
use App\Http\Requests\Concerns\ValidatesUniqueDashboardPhone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreSchoolRequest extends FormRequest
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
            'schoolNameAr' => ['required', 'string', 'max:255'],
            'schoolNameEn' => ['required', 'string', 'max:255'],
            'province' => ['required', 'string', 'max:255'],
            'district' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'status' => ['required', 'string', 'max:32'],
            'principalName' => ['nullable', 'string', 'max:255'],
            'adminPhone' => ['nullable', 'string', 'size:10', 'regex:/^[1-9]\d{9}$/'],
            'authorizedPersonName' => ['nullable', 'string', 'max:255'],
            'authorizedPersonPhone' => ['nullable', 'string', 'size:10', 'regex:/^[1-9]\d{9}$/'],
            'complaintsSupportPhone' => ['nullable', 'string', 'min:3', 'max:32'],
            'complaintsSupportWhatsapp' => ['nullable', 'string', 'min:3', 'max:32'],
            'complaintsSupportHours' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'attachment' => ['nullable', 'file', 'max:4096'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $this->assertUniqueDashboardPhone($validator, 'adminPhone', PhoneAccountType::School);
        $this->assertUniqueDashboardPhone($validator, 'authorizedPersonPhone', PhoneAccountType::School);
    }
}
