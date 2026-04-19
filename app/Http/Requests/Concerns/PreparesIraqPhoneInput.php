<?php

namespace App\Http\Requests\Concerns;

trait PreparesIraqPhoneInput
{
    protected function prepareForValidation(): void
    {
        if (! $this->has('phone')) {
            return;
        }

        $this->merge([
            'phone' => preg_replace('/\D+/', '', (string) $this->input('phone')),
        ]);
    }
}
