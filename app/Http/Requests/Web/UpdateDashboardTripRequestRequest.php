<?php

namespace App\Http\Requests\Web;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDashboardTripRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return (bool) ($user?->canMutateSchoolRoster());
    }

    /** @return array<string, list<string|ValidationRule>> */
    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'driver_id' => ['nullable', 'integer', 'exists:drivers,id'],
            'trip_history_id' => ['nullable', 'integer', 'exists:trip_histories,id'],
            'status' => ['required', 'string', 'max:32', Rule::in(['pending', 'accepted', 'rejected', 'cancelled'])],
            'notes' => ['nullable', 'string', 'max:2000'],
            'present_type' => ['nullable', 'string', 'max:64'],
            'moving_point' => ['nullable', 'string', 'max:2000'],
            'stop_point' => ['nullable', 'string', 'max:2000'],
            'subscribe_price' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
