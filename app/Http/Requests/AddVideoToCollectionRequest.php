<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use JetBrains\PhpStorm\ArrayShape;

class AddVideoToCollectionRequest extends FormRequest
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
        'video_id' => "string",
        'position' => "string",
        'curator_notes' => "string",
    ])] public function rules(): array
    {
        return [
            'video_id' => 'required|integer|exists:videos,id',
            'position' => 'nullable|integer|min:0',
            'curator_notes' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    #[ArrayShape([
        'video_id.required' => "string",
        'video_id.exists' => "string",
        'position.integer' => "string",
        'position.min' => "string",
        'curator_notes.max' => "string"
    ])] public function messages(): array
    {
        return [
            'video_id.required' => 'Video ID is required.',
            'video_id.exists' => 'The selected video does not exist.',
            'position.integer' => 'Position must be a valid number.',
            'position.min' => 'Position cannot be negative.',
            'curator_notes.max' => 'Curator notes cannot exceed 1000 characters.',
        ];
    }
}
