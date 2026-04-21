<?php

namespace App\Http\Requests\Web;

use App\Http\Requests\Concerns\PreparesIraqPhoneInput;
use App\Models\User;
use App\Services\Phone\PhoneNormalizer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreDashboardUserRequest extends FormRequest
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
            'name' => ['nullable', 'string', 'max:255'],
            'image' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:1536'],
            'phone' => ['required', 'string', 'size:10', 'regex:/^[1-9]\\d{9}$/'],
            'city' => ['nullable', 'string', 'max:255'],
            'licence_number' => ['nullable', 'string', 'max:255'],
            'votes' => ['required', 'integer', 'min:0'],
            'rate' => ['required', 'numeric', 'min:0', 'max:5'],
            'is_verified' => ['nullable', 'boolean'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $phone = app(PhoneNormalizer::class)->normalize((string) $this->input('phone', ''));

            if (User::query()->where('phone', $phone)->exists()) {
                $validator->errors()->add('phone', __('validation.unique', ['attribute' => __('dashboard.phone')]));
            }
        });
    }

    protected function passedValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'is_verified' => $this->boolean('is_verified'),
        ]);
    }
}
