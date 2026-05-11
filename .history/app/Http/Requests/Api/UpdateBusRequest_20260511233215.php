<?php

namespace App\Http\Requests\Api;

use App\Models\Bus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string|ValidationRule>> */
    public function rules(): array
    {
        $driverId = $this->user()?->driver?->id;
        $bus = $driverId ? Bus::query()->where('driver_id', $driverId)->first() : null;

        return [
            'busName' => ['sometimes', 'string', 'max:255'],
            'busRouteDescription' => ['sometimes', 'nullable', 'string', 'max:512'],
            'busType' => ['sometimes', 'string', 'max:255'],
            'busCity' => ['sometimes', 'string', 'max:255'],
            'busNumber' => ['sometimes', 'string', 'max:255', Rule::unique('buses', 'number')->ignore($bus?->id)],
            'busColor' => ['sometimes', 'string', 'max:255'],
            'busCapacity' => ['sometimes', 'integer', 'min:1'],
            'fuelType' => ['sometimes', 'string', 'max:255'],
            'busStatus' => ['sometimes', 'string', 'max:255'],
            'busAnnualStatus' => ['sometimes', 'boolean'],
            'busInsurance' => ['sometimes', 'boolean'],
            'busModelYear' => ['sometimes', 'nullable', 'integer', 'min:1980', 'max:'.(date('Y') + 1)],
            'busAcStatus' => ['sometimes', 'nullable', 'string', 'in:yes,no'],
        ];
    }
}
