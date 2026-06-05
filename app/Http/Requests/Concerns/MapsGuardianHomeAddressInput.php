<?php

namespace App\Http\Requests\Concerns;

trait MapsGuardianHomeAddressInput
{
    protected function mapGuardianHomeAddressInput(): void
    {
        $district = trim((string) $this->input('home_district_area', ''));
        $landmark = trim((string) $this->input('home_nearest_landmark', ''));
        $legacyAddress = trim((string) $this->input('home_formatted_address', ''));

        if ($landmark === '' && $legacyAddress !== '') {
            $landmark = $legacyAddress;
        }

        if ($district === '' && $landmark !== '') {
            $district = $landmark;
        }

        if ($landmark === '' && $district !== '') {
            $landmark = $district;
        }

        $formatted = $landmark;
        if ($district !== '' && $landmark !== '' && $district !== $landmark) {
            $formatted = $landmark;
        }

        $this->merge([
            'home_district_area' => $district,
            'home_nearest_landmark' => $landmark,
            'home_formatted_address' => $formatted !== '' ? $formatted : null,
        ]);
    }
}
