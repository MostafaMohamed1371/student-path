<?php

namespace App\Http\Requests\Concerns;

use App\Services\IdCard\DashboardIdCardRegistry;
use App\Services\IdCard\IdCardRecordIdentity;
use Illuminate\Validation\Validator;

trait ValidatesUniqueDashboardIdCard
{
    protected function assertUniqueDashboardIdCard(
        Validator $validator,
        string $field,
        ?IdCardRecordIdentity $except = null,
    ): void {
        $validator->after(function (Validator $validator) use ($field, $except): void {
            if ($validator->errors()->has($field)) {
                return;
            }

            $raw = (string) $this->input($field, '');
            if (trim($raw) === '') {
                return;
            }

            try {
                app(DashboardIdCardRegistry::class)->assertAvailable($raw, $except, $field);
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
