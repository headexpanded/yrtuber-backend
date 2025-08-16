<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use JetBrains\PhpStorm\ArrayShape;

class NotificationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Will be checked in controller
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('read')) {
            $value = filter_var($this->read, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($value !== null) {
                $this->merge(['read' => $value]);
            }
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    #[ArrayShape(['per_page' => "string[]", 'type' => "string[]", 'read' => "string[]"])] public function rules(): array
    {
        return [
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'type' => ['sometimes', 'nullable', 'string', 'regex:/^[a-zA-Z_-]+$/'],
            'read' => ['sometimes', 'nullable', 'boolean'],
        ];
    }

    /**
     * Get custom error messages for validator errors.
     */
    #[ArrayShape([
        'per_page.min' => "string",
        'per_page.max' => "string",
        'type.string' => "string",
        'read.boolean' => "string",
    ])] public function messages(): array
    {
        return [
            'per_page.min' => 'The per page value must be at least 1.',
            'per_page.max' => 'The per page value may not be greater than 100.',
            'type.string' => 'The notification type must be a string.',
            'read.boolean' => 'The read status must be true or false.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    #[ArrayShape(['per_page' => "string", 'type' => "string", 'read' => "string"])] public function attributes(): array
    {
        return [
            'per_page' => 'per page',
            'type' => 'notification type',
            'read' => 'read status',
        ];
    }
}
