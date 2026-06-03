<?php

namespace App\Http\Requests\Web;

use App\Enums\PhoneAccountType;
use App\Http\Requests\Concerns\ValidatesUniqueDashboardPhone;
use App\Models\Guardian;
use App\Services\Phone\PhoneRecordIdentity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateDashboardGuardianRequest extends FormRequest
{
    use ValidatesUniqueDashboardPhone;
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['phone', 'backup_phone'] as $field) {
            if ($this->has($field)) {
                $this->merge([
                    $field => preg_replace('/\D+/', '', (string) $this->input($field)),
                ]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'school_id' => ['required', 'integer', 'exists:schools,id'],
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'regex:/^[1-9][0-9]{9}$/'],
            'backup_phone' => ['nullable', 'regex:/^[1-9][0-9]{9}$/'],
            'id_card_number' => ['nullable', 'string', 'max:64'],
            'status' => ['required', 'in:active,inactive'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        /** @var Guardian $guardian */
        $guardian = $this->route('guardian');

        $this->assertUniqueDashboardPhone(
            $validator,
            'phone',
            PhoneAccountType::Guardian,
            new PhoneRecordIdentity(guardianId: (int) $guardian->id, guardianPhoneField: 'phone'),
        );
        $this->assertUniqueDashboardPhone(
            $validator,
            'backup_phone',
            PhoneAccountType::Guardian,
            new PhoneRecordIdentity(guardianId: (int) $guardian->id, guardianPhoneField: 'backup_phone'),
        );
    }
}
