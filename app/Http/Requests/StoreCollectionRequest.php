<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use JetBrains\PhpStorm\ArrayShape;

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
     * @return array<string, ValidationRule|array|string>
     */
    #[ArrayShape([
        'title' => "string",
        'slug' => "string",
        'description' => "string",
        'cover_image' => "string",
        'layout' => "array",
        'is_public' => "string",
        'is_published' => "string",
        'is_featured' => "string",
        'tags' => "string",
        'tags.*' => "string"
    ])] public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:collections,slug',
            'description' => 'nullable|string|max:2000',
            'cover_image' => 'nullable|string|max:255',
            'layout' => ['required', Rule::in(['grid', 'list', 'carousel', 'magazine'])],
            'is_public' => 'boolean',
            'is_published' => 'boolean',
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
    #[ArrayShape([
        'title.required' => "string",
        'title.max' => "string",
        'slug.unique' => "string",
        'description.max' => "string",
        'layout.in' => "string",
        'tags.*.exists' => "string"
    ])] public function messages(): array
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
