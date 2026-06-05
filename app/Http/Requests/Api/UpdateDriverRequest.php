<?php

namespace App\Http\Requests\Api;

use App\Enums\PhoneAccountType;
use App\Http\Requests\Concerns\ValidatesUniqueDashboardPhone;
use App\Models\Driver;
use App\Services\Phone\PhoneRecordIdentity;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateDriverRequest extends FormRequest
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
            'firstName' => ['sometimes', 'string', 'max:255'],
            'fatherName' => ['sometimes', 'string', 'max:255'],
            'grandfatherName' => ['sometimes', 'string', 'max:255'],
            'lastName' => ['sometimes', 'string', 'max:255'],
            'age' => ['sometimes', 'integer', 'min:18', 'max:80'],
            'idCardNumber' => ['sometimes', 'string', 'max:255'],
            'licenseNumber' => ['sometimes', 'string', 'max:255'],
            'primaryPhone' => ['sometimes', 'string', 'size:10', 'regex:/^[1-9]\d{9}$/'],
            'emergencyPhone' => ['sometimes', 'string', 'size:10', 'regex:/^[1-9]\d{9}$/'],
            'residentialAddress' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', 'in:active,inactive'],
            'monthlySubscriptionPrice' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:999999999999'],
            'idCardImage' => ['sometimes', 'nullable', 'file', 'image', 'max:4096'],
            'licenseImage' => ['sometimes', 'nullable', 'file', 'image', 'max:4096'],
            'nonConvictionCertificate' => ['sometimes', 'nullable', 'file', 'max:4096'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        if (! $this->hasAny(['primaryPhone', 'emergencyPhone'])) {
            return;
        }

        /** @var Driver $driver */
        $driver = $this->route('driver');

        $driverUserId = $driver->user_id ? (int) $driver->user_id : null;

        if ($this->filled('primaryPhone')) {
            $this->assertUniqueDashboardPhone(
                $validator,
                'primaryPhone',
                PhoneAccountType::Driver,
                new PhoneRecordIdentity(
                    driverId: (int) $driver->id,
                    driverUserId: $driverUserId,
                    driverPhoneField: 'primary_phone',
                ),
                $driver->primary_phone,
            );
        }

        if ($this->filled('emergencyPhone')) {
            $this->assertUniqueDashboardPhone(
                $validator,
                'emergencyPhone',
                PhoneAccountType::Driver,
                new PhoneRecordIdentity(
                    driverId: (int) $driver->id,
                    driverUserId: $driverUserId,
                    driverPhoneField: 'emergency_phone',
                ),
                $driver->emergency_phone,
            );
        }
    }
}
