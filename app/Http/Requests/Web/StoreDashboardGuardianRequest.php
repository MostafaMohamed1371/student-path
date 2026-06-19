<?php

namespace App\Http\Requests\Web;

use App\Enums\PhoneAccountType;
use App\Http\Requests\Concerns\MapsGuardianHomeAddressInput;
use App\Http\Requests\Concerns\ValidatesDashboardIraqLocation;
use App\Http\Requests\Concerns\ValidatesOptionalGuardianHomeLocation;
use App\Http\Requests\Concerns\ValidatesUniqueDashboardIdCard;
use App\Http\Requests\Concerns\ValidatesUniqueDashboardPhone;
use App\Services\IdCard\IdCardRecordIdentity;
use App\Services\Phone\PhoneRecordIdentity;
use App\Support\IdCardNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreDashboardGuardianRequest extends FormRequest
{
    use MapsGuardianHomeAddressInput;
    use ValidatesDashboardIraqLocation;
    use ValidatesOptionalGuardianHomeLocation;
    use ValidatesUniqueDashboardIdCard;
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

        if ($this->has('id_card_number')) {
            $this->merge([
                'id_card_number' => IdCardNumber::normalize($this->input('id_card_number')),
            ]);
        }

        $this->mapGuardianHomeAddressInput();
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
            ...$this->dashboardIraqLocationRules('home_'),
            ...$this->optionalGuardianHomeLocationRules(),
        ];
    }

    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);

        if ($key !== null) {
            return $validated;
        }

        return $this->mergeResolvedGuardianHomeIraqLocationAttributes($validated);
    }

    public function withValidator(Validator $validator): void
    {
        $schoolId = (int) $this->input('school_id');
        $idCard = IdCardNumber::normalize($this->input('id_card_number'));
        $phoneContext = new PhoneRecordIdentity(
            guardianSchoolId: $schoolId,
            guardianIdCardNumber: $idCard,
        );

        $this->assertUniqueDashboardPhone($validator, 'phone', PhoneAccountType::Guardian, $phoneContext);
        $this->assertUniqueDashboardPhone($validator, 'backup_phone', PhoneAccountType::Guardian, $phoneContext);
        $this->assertUniqueDashboardIdCard(
            $validator,
            'id_card_number',
            new IdCardRecordIdentity(guardianSchoolId: $schoolId),
        );
    }
}
