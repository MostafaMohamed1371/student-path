<?php

namespace App\Http\Requests\Concerns;

use App\Models\Bus;
use Illuminate\Validation\Validator;

trait ValidatesDriverBusAssignment
{
    protected function prepareDriverBusIdInput(): void
    {
        if ($this->input('bus_id') === '' || $this->input('bus_id') === null) {
            $this->merge(['bus_id' => null]);
        }
    }

    protected function assertDriverBusAvailable(Validator $validator, string $field = 'bus_id'): void
    {
        $validator->after(function (Validator $validator) use ($field): void {
            if ($validator->errors()->has($field)) {
                return;
            }

            $busId = (int) $this->input($field, 0);
            if ($busId <= 0) {
                return;
            }

            $schoolId = (int) $this->input('school_id', 0);

            $bus = Bus::query()->whereKey($busId)->first();
            if ($bus === null) {
                return;
            }

            if (! $bus->belongsToSchool($schoolId)) {
                $validator->errors()->add($field, __('dashboard.driver_bus_not_in_school'));
            }
        });
    }
}
