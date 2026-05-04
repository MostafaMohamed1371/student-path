<?php

namespace App\Http\Requests\Web;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDashboardSupportComplaintRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    /** @return array<string, list<string|ValidationRule>> */
    public function rules(): array
    {
        $allowedIds = collect(config('mobile_legacy_api.support.categories', []))
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        return [
            'category_id' => ['required', 'string', 'max:64', Rule::in($allowedIds)],
            'details' => ['required', 'string', 'max:5000'],
            'status' => ['required', 'string', 'max:32', Rule::in(['RECEIVED', 'IN_REVIEW', 'RESOLVED', 'CLOSED'])],
        ];
    }
}
