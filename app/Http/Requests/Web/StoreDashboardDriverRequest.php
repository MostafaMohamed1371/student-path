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
            'status' => ['required', 'in:active,inactive'],
            'monthly_subscription_price' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
            'id_card_image' => ['nullable', 'file', 'image', 'max:4096'],
            'license_image' => ['nullable', 'file', 'image', 'max:4096'],
            'non_conviction_certificate' => ['nullable', 'file', 'max:4096'],
        ];
    }
}
