<?php

namespace App\Http\Requests\Concerns;

use App\Enums\PhoneAccountType;
use App\Services\Phone\DashboardPhoneRegistry;
use App\Services\Phone\PhoneRecordIdentity;
use Illuminate\Validation\Validator;

trait ValidatesUniqueDashboardPhone
{
    protected function assertUniqueDashboardPhone(
        Validator $validator,
        string $field,
        PhoneAccountType $type,
        ?PhoneRecordIdentity $except = null,
        mixed $originalPhone = null,
    ): void {
        $validator->after(function (Validator $validator) use ($field, $type, $except, $originalPhone): void {
            if ($validator->errors()->has($field)) {
                return;
            }

            $registry = app(DashboardPhoneRegistry::class);
            $raw = $registry->nationalDigits((string) $this->input($field, ''));
            if ($raw === '' || ! app(\App\Services\Phone\PhoneNormalizer::class)->isValidIraqiMobile($raw)) {
                return;
            }

            if ($originalPhone !== null && $registry->phonesMatch($raw, $originalPhone)) {
                return;
            }

            try {
                $registry->assertAvailable($raw, $type, $except, $field);
            } catch (\Illuminate\Validation\ValidationException $e) {
                foreach ($e->errors() as $key => $messages) {
                    foreach ($messages as $message) {
                        $validator->errors()->add($key, $message);
                    }
                }
            }
        });
    }
}
