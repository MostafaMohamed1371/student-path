<?php

namespace App\Http\Requests\Web;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreDashboardDriverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['rating_avg', 'rating_count', 'route_description'] as $field) {
            if ($this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }
    }

    /** @return array<string, list<string|ValidationRule>> */
    public function rules(): array
    {
        return [
            'school_id' => ['required', 'integer', 'exists:schools,id'],
            'first_name' => ['required', 'string', 'max:255'],
            'father_name' => ['required', 'string', 'max:255'],
            'grandfather_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'age' => ['required', 'integer', 'min:18', 'max:80'],
            'id_card_number' => ['required', 'string', 'max:255'],
            'license_number' => ['required', 'string', 'max:255'],
            'primary_phone' => ['required', 'string', 'size:10', 'regex:/^[1-9]\d{9}$/'],
            'emergency_phone' => ['required', 'string', 'size:10', 'regex:/^[1-9]\d{9}$/'],
            'residential_address' => ['required', 'string', 'max:255'],
            'route_description' => ['nullable', 'string', 'max:512'],
            'status' => ['required', 'in:active,inactive'],
            'monthly_subscription_price' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
            'shift_period' => ['nullable', 'in:MORNING,EVENING'],
            'profile_image' => ['nullable', 'file', 'image', 'max:4096'],
            'id_card_image' => ['nullable', 'file', 'image', 'max:4096'],
            'license_image' => ['nullable', 'file', 'image', 'max:4096'],
            'non_conviction_certificate' => ['nullable', 'file', 'max:4096'],
            'rating_avg' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'rating_count' => ['nullable', 'integer', 'min:0', 'max:999999'],
        ];
    }
}
