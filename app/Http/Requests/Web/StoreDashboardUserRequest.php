<?php

namespace App\Http\Requests\Web;

use App\Enums\PhoneAccountType;
use App\Http\Requests\Concerns\PreparesIraqPhoneInput;
use App\Http\Requests\Concerns\ValidatesUniqueDashboardPhone;
use App\Services\Phone\PhoneNormalizer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreDashboardUserRequest extends FormRequest
{
    use PreparesIraqPhoneInput;
    use ValidatesUniqueDashboardPhone;

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
            'name' => ['nullable', 'string', 'max:255'],
            'school_id' => ['required', 'integer', 'exists:schools,id'],
            'image' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:1536'],
            'phone' => ['required', 'string', 'size:10', 'regex:/^[1-9]\\d{9}$/'],
            'city' => ['nullable', 'string', 'max:255'],
            'licence_number' => ['nullable', 'string', 'max:255'],
            'votes' => ['required', 'integer', 'min:0'],
            'rate' => ['required', 'numeric', 'min:0', 'max:5'],
            'is_verified' => ['nullable', 'boolean'],
            'is_admin' => ['nullable', 'boolean'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $type = $this->boolean('is_admin') ? PhoneAccountType::Admin : PhoneAccountType::School;
        $this->assertUniqueDashboardPhone($validator, 'phone', $type);
    }

    protected function passedValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'is_verified' => $this->boolean('is_verified'),
            'is_admin' => $this->boolean('is_admin'),
        ]);
    }
}
