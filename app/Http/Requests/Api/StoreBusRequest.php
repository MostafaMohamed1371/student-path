<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreBusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string|ValidationRule>> */
    public function rules(): array
    {
        return [
            'busName' => ['required', 'string', 'max:255'],
            'busType' => ['required', 'string', 'max:255'],
            'busCity' => ['required', 'string', 'max:255'],
            'busNumber' => ['required', 'string', 'max:255', 'unique:buses,number'],
            // Color is saved as a HEX string selected from a color picker (e.g. #FFD700).
            'busColor' => ['required', 'string', 'max:255'],
            'busCapacity' => ['required', 'integer', 'min:1'],
            'fuelType' => ['required', 'string', 'max:255'],
            'busStatus' => ['required', 'string', 'max:255'],
            'busAnnualStatus' => ['required', 'boolean'],
            'busInsurance' => ['required', 'boolean'],
        ];
    }
}
