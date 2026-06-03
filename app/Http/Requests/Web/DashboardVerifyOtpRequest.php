<?php

namespace App\Http\Requests\Web;

use Illuminate\Contracts\Validation\ValidationRule;

class DashboardVerifyOtpRequest extends DashboardPhoneRequest
{
    /**
     * @return array<string, list<string|ValidationRule>>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'code' => ['required', 'string', 'regex:/^\d{4}$/'],
        ];
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        if ($this->has('code')) {
            $this->merge([
                'code' => preg_replace('/\D+/', '', (string) $this->input('code')),
            ]);
        }
    }
}
