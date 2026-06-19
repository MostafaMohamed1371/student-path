<?php

namespace App\Http\Requests\Concerns;

trait ValidatesOptionalGuardianHomeLocation
{
    /**
     * @return array<string, list<string>>
     */
    protected function optionalGuardianHomeLocationRules(): array
    {
        return [
            'home_latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:home_longitude'],
            'home_longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:home_latitude'],
            'home_nearest_landmark' => ['nullable', 'string', 'max:255'],
            'home_formatted_address' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
