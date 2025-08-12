<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
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
    public function messages(): array
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
