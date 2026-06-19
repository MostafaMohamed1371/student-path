<?php

namespace App\Http\Requests\Concerns;

use App\Services\Locations\IraqLocationAttributeResolver;

trait ValidatesDashboardIraqLocation
{
    /**
     * @return array<string, list<string>>
     */
    protected function dashboardIraqLocationRules(string $fieldPrefix = '', bool $required = true): array
    {
        $req = $required ? 'required' : 'nullable';

        return [
            $fieldPrefix.'district_id' => [$req, 'integer', 'exists:districts,id'],
            $fieldPrefix.'area_id' => [$req, 'integer', 'exists:areas,id'],
            $fieldPrefix.'neighborhood_id' => [$req, 'integer', 'exists:neighborhoods,id'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function mergeResolvedIraqLocationAttributes(array $validated, string $fieldPrefix = ''): array
    {
        $resolved = app(IraqLocationAttributeResolver::class)->resolve($validated, $fieldPrefix);

        $validated['district_id'] = $resolved['district_id'];
        $validated['area_id'] = $resolved['area_id'];
        $validated['neighborhood_id'] = $resolved['neighborhood_id'];

        if ($resolved['district_area'] !== null) {
            $validated['district_area'] = $resolved['district_area'];
        }

        unset(
            $validated[$fieldPrefix.'district_id'],
            $validated[$fieldPrefix.'area_id'],
            $validated[$fieldPrefix.'neighborhood_id'],
        );

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function mergeResolvedGuardianHomeIraqLocationAttributes(array $validated): array
    {
        $resolved = app(IraqLocationAttributeResolver::class)->resolve($validated, 'home_');

        $validated['home_district_id'] = $resolved['district_id'];
        $validated['home_area_id'] = $resolved['area_id'];
        $validated['home_neighborhood_id'] = $resolved['neighborhood_id'];

        if ($resolved['district_area'] !== null) {
            $validated['home_district_area'] = $resolved['district_area'];
        }

        return $validated;
    }
}
