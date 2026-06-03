<?php

namespace App\Http\Requests\Web;

use App\Http\Requests\Concerns\PreparesIraqPhoneInput;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DashboardPhoneRequest extends FormRequest
{
    use PreparesIraqPhoneInput;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string|ValidationRule>>
     */
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'size:10', 'regex:/^[1-9]\d{9}$/'],
        ];
    }
}
