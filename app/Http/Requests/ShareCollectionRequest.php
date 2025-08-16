<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use JetBrains\PhpStorm\ArrayShape;

class ShareCollectionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Will be checked in controller
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    #[ArrayShape([
        'platform' => "array",
        'share_type' => "array",
        'expires_at' => "string[]",
        'custom_url' => "string[]"
    ])] public function rules(): array
    {
        return [
            'platform' => ['required', 'string', Rule::in(['twitter', 'facebook', 'linkedin', 'email', 'link'])],
            'share_type' => ['sometimes', 'string', Rule::in(['public', 'private', 'temporary'])],
            'expires_at' => ['sometimes', 'nullable', 'date', 'after:now'],
            'custom_url' => ['sometimes', 'nullable', 'url'],
        ];
    }

    /**
     * Get custom error messages for validator errors.
     */
    #[ArrayShape([
        'platform.required' => "string",
        'platform.in' => "string",
        'share_type.in' => "string",
        'expires_at.after' => "string",
        'custom_url.url' => "string"
    ])] public function messages(): array
    {
        return [
            'platform.required' => 'Please select a sharing platform.',
            'platform.in' => 'The selected platform is not supported.',
            'share_type.in' => 'The selected share type is not valid.',
            'expires_at.after' => 'The expiration date must be in the future.',
            'custom_url.url' => 'Please provide a valid URL.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    #[ArrayShape([
        'platform' => "string",
        'share_type' => "string",
        'expires_at' => "string",
        'custom_url' => "string"
    ])] public function attributes(): array
    {
        return [
            'platform' => 'sharing platform',
            'share_type' => 'share type',
            'expires_at' => 'expiration date',
            'custom_url' => 'custom URL',
        ];
    }
}
