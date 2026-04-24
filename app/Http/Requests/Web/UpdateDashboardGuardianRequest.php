<?php

namespace App\Http\Requests\Web;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDashboardGuardianRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'school_id' => ['required', 'integer', 'exists:schools,id'],
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'regex:/^[1-9][0-9]{9}$/'],
            'backup_phone' => ['nullable', 'regex:/^[1-9][0-9]{9}$/'],
            'id_card_number' => ['nullable', 'string', 'max:64'],
            'status' => ['required', 'in:active,inactive'],
        ];
    }
}
