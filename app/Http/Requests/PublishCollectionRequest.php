<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use JetBrains\PhpStorm\ArrayShape;

class PublishCollectionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    #[ArrayShape([
        'is_published' => "string"
    ])] public function rules(): array
    {
        return [
            'is_published' => 'required|boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    #[ArrayShape([
        'is_published.required' => "string",
        'is_published.boolean' => "string"
    ])] public function messages(): array
    {
        return [
            'is_published.required' => 'The publish status is required.',
            'is_published.boolean' => 'The publish status must be true or false.',
        ];
    }
}
