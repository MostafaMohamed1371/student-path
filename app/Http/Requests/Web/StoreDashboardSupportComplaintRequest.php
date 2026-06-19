<?php

namespace App\Http\Requests\Web;

use App\Services\Support\SupportComplaintAttachmentStore;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDashboardSupportComplaintRequest extends FormRequest
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

        return array_merge([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'category_id' => ['required', 'string', 'max:64', Rule::in($allowedIds)],
            'details' => ['required', 'string', 'max:5000'],
        ], app(SupportComplaintAttachmentStore::class)->validationRules());
    }
}
