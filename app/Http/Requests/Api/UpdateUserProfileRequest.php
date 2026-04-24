<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateUserProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string|ValidationRule>> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'schoolId' => ['sometimes', 'nullable', 'integer', 'exists:schools,id'],
            'image' => ['sometimes', 'nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:1536'],
            'city' => ['sometimes', 'nullable', 'string', 'max:255'],
            'licenceNumber' => ['sometimes', 'nullable', 'string', 'max:255'],
            'votes' => ['sometimes', 'integer', 'min:0'],
            'rate' => ['sometimes', 'numeric', 'min:0', 'max:5'],
            'isVerified' => ['sometimes', 'boolean'],
        ];
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
