<?php

namespace App\Http\Requests\Api;

use App\Http\Requests\Concerns\PreparesIraqPhoneInput;
use App\Http\Requests\Concerns\ValidatesUniqueDashboardPhone;
use App\Services\Phone\DashboardPhoneRegistry;
use App\Services\Phone\PhoneRecordIdentity;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateUserProfileRequest extends FormRequest
{
    use PreparesIraqPhoneInput;
    use ValidatesUniqueDashboardPhone;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string|ValidationRule>> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user()?->id)],
            'phone' => ['sometimes', 'string', 'size:10', 'regex:/^[1-9]\d{9}$/'],
            'schoolId' => ['sometimes', 'nullable', 'integer', 'exists:schools,id'],
            'image' => ['sometimes', 'nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:1536'],
            'city' => ['sometimes', 'nullable', 'string', 'max:255'],
            'licenceNumber' => ['sometimes', 'nullable', 'string', 'max:255'],
            'votes' => ['sometimes', 'integer', 'min:0'],
            'rate' => ['sometimes', 'numeric', 'min:0', 'max:5'],
            'isVerified' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $user = $this->user();
        if ($user === null || ! $this->filled('phone')) {
            return;
        }

        $user->loadMissing(['driver', 'guardian']);
        $registry = app(DashboardPhoneRegistry::class);

        $this->assertUniqueDashboardPhone(
            $validator,
            'phone',
            $registry->accountTypeForUser($user),
            new PhoneRecordIdentity(
                userId: (int) $user->id,
                schoolId: $user->school_id ? (int) $user->school_id : null,
                driverId: $user->driver ? (int) $user->driver->id : null,
                driverUserId: $user->driver ? (int) $user->id : null,
                driverPhoneField: $user->driver ? 'primary_phone' : null,
                guardianId: $user->guardian_id ? (int) $user->guardian_id : null,
                guardianPhoneField: $user->guardian_id ? 'phone' : null,
            ),
            $user->phone,
        );
    }

    public function messages(): array
    {
        return [
            'image.uploaded' => 'Image upload failed. Please choose a smaller file and try again.',
            'image.max' => 'Image size must be less than 1.5 MB.',
            'image.image' => 'The selected file must be an image.',
            'image.mimes' => 'Allowed image formats: jpg, jpeg, png, webp.',
            'phone.size' => 'Enter exactly 10 digits for your mobile number (country code 964 is added automatically).',
            'phone.regex' => 'The mobile number must be 10 digits and cannot start with 0.',
        ];
    }
}
