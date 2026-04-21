<?php

namespace App\Http\Requests\Web;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMyProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string|ValidationRule>> */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'image' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:1536'],
            'city' => ['nullable', 'string', 'max:255'],
            'licence_number' => ['nullable', 'string', 'max:255'],
            'votes' => ['required', 'integer', 'min:0'],
            'rate' => ['required', 'numeric', 'min:0', 'max:5'],
            'is_verified' => ['nullable', 'boolean'],
        ];
    }

    protected function passedValidation(): void
    {
        $this->merge([
            'is_verified' => $this->boolean('is_verified'),
        ]);
    }

    public function messages(): array
    {
        return [
            'image.uploaded' => 'Image upload failed. Please choose a smaller file and try again.',
            'image.max' => 'Image size must be less than 1.5 MB.',
            'image.image' => 'The selected file must be an image.',
            'image.mimes' => 'Allowed image formats: jpg, jpeg, png, webp.',
        ];
    }
}
