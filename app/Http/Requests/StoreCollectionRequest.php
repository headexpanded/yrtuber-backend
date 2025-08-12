<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCollectionRequest extends FormRequest
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
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:collections,slug',
            'description' => 'nullable|string|max:2000',
            'cover_image' => 'nullable|string|max:255',
            'layout' => ['required', Rule::in(['grid', 'list', 'carousel', 'magazine'])],
            'is_public' => 'boolean',
            'is_featured' => 'boolean',
            'tags' => 'nullable|array',
            'tags.*' => 'integer|exists:tags,id',
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
            'title.required' => 'A collection title is required.',
            'title.max' => 'The collection title cannot exceed 255 characters.',
            'slug.unique' => 'This collection slug is already taken.',
            'description.max' => 'The description cannot exceed 2000 characters.',
            'layout.in' => 'Please select a valid layout option.',
            'tags.*.exists' => 'One or more selected tags do not exist.',
        ];
    }
}
