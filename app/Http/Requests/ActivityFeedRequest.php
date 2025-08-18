<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use JetBrains\PhpStorm\ArrayShape;

class ActivityFeedRequest extends FormRequest
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
        'per_page' => "string[]",
        'feed_type' => "array",
        'action' => "string[]",
        'subject_type' => "string[]",
        'user_id' => "string[]",
        'period' => "array"
    ])] public function rules(): array
    {
        return [
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'feed_type' => ['sometimes', 'string', Rule::in(['personalized', 'global', 'user', 'targeted'])],
            'action' => ['sometimes', 'nullable', 'string', 'regex:/^[a-zA-Z._-]+$/'],
            'subject_type' => ['sometimes', 'nullable', 'string', 'regex:/^[a-zA-Z\\\\._-]+$/'],
            'user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'period' => ['sometimes', 'string', Rule::in(['hour', 'day', 'week', 'month', 'year', 'all'])],
        ];
    }

    /**
     * Get custom error messages for validator errors.
     */
    #[ArrayShape([
        'per_page.min' => "string",
        'per_page.max' => "string",
        'feed_type.in' => "string",
        'action.string' => "string",
        'subject_type.string' => "string",
        'user_id.exists' => "string",
        'period.in' => "string"
    ])] public function messages(): array
    {
        return [
            'per_page.min' => 'The per page value must be at least 1.',
            'per_page.max' => 'The per page value may not be greater than 100.',
            'feed_type.in' => 'The selected feed type is not valid.',
            'action.string' => 'The action must be a string.',
            'subject_type.string' => 'The subject type must be a string.',
            'user_id.exists' => 'The selected user does not exist.',
            'period.in' => 'The selected period is not valid.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    #[ArrayShape([
        'per_page' => "string",
        'feed_type' => "string",
        'action' => "string",
        'subject_type' => "string",
        'user_id' => "string"
    ])] public function attributes(): array
    {
        return [
            'per_page' => 'per page',
            'feed_type' => 'feed type',
            'action' => 'action',
            'subject_type' => 'subject type',
            'user_id' => 'user',
        ];
    }
}
