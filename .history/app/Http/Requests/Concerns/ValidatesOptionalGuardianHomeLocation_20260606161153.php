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
            'district_id' => ['nullable', 'integer', 'exists:districts,id'],
            'area_id' => ['nullable', 'integer', 'exists:areas,id'],
            'neighborhood_ids' => ['nullable', 'array'],
            'neighborhood_ids.*' => ['integer', 'exists:neighborhoods,id'],
            'home_district_area' => ['nullable', 'string', 'max:255'],
            'home_nearest_landmark' => ['nullable', 'string', 'max:255'],
            'home_formatted_address' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
