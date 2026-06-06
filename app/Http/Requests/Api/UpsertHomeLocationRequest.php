<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpsertHomeLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $formattedAddress = $this->input('formatted_address', $this->input('formattedAddress'));
        $districtArea = $this->input('district_area', $this->input('districtArea'));
        $nearestLandmark = $this->input('nearest_landmark', $this->input('nearestLandmark'));
        $placeId = $this->input('place_id', $this->input('placeId'));

        $landmark = trim((string) ($nearestLandmark ?? $formattedAddress ?? ''));
        $district = trim((string) ($districtArea ?? ''));

        if ($district === '' && $landmark !== '') {
            $district = $landmark;
        }

        if ($landmark === '' && $district !== '') {
            $landmark = $district;
        }

        $this->merge([
            'formatted_address' => $landmark !== '' ? $landmark : (is_string($formattedAddress) ? trim($formattedAddress) : null),
            'district_area' => $district !== '' ? $district : null,
            'nearest_landmark' => $landmark !== '' ? $landmark : null,
            'place_id' => is_string($placeId) ? trim($placeId) : $placeId,
        ]);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'formatted_address' => ['nullable', 'string', 'max:2000'],
            'district_area' => ['nullable', 'string', 'max:255'],
            'nearest_landmark' => ['nullable', 'string', 'max:255'],
            'place_id' => ['nullable', 'string', 'max:255'],
        ];
    }
}
