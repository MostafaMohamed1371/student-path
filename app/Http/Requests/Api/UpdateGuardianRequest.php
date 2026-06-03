<?php

namespace App\Http\Requests\Api;

use App\Enums\PhoneAccountType;
use App\Http\Requests\Concerns\ValidatesUniqueDashboardPhone;
use App\Models\Guardian;
use App\Services\Phone\PhoneRecordIdentity;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateGuardianRequest extends FormRequest
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
            'fullName' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'string', 'size:10', 'regex:/^[1-9]\d{9}$/'],
            'backupPhone' => ['sometimes', 'nullable', 'string', 'size:10', 'regex:/^[1-9]\d{9}$/'],
            'idCardNumber' => ['sometimes', 'nullable', 'string', 'max:64'],
            'status' => ['sometimes', 'in:active,inactive'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        /** @var Guardian $guardian */
        $guardian = $this->route('guardian');
        $guardianId = (int) $guardian->id;

        if ($this->filled('phone')) {
            $this->assertUniqueDashboardPhone(
                $validator,
                'phone',
                PhoneAccountType::Guardian,
                new PhoneRecordIdentity(guardianId: $guardianId, guardianPhoneField: 'phone'),
            );
        }

        if ($this->filled('backupPhone')) {
            $this->assertUniqueDashboardPhone(
                $validator,
                'backupPhone',
                PhoneAccountType::Guardian,
                new PhoneRecordIdentity(guardianId: $guardianId, guardianPhoneField: 'backup_phone'),
            );
        }
    }
}
