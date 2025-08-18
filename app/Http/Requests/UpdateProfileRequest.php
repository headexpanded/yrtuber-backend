<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use JetBrains\PhpStorm\ArrayShape;

class UpdateProfileRequest extends FormRequest
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
    public function rules(): array
    {
        return [
            'username' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('user_profiles', 'username')->ignore($this->user()->profile?->id),
            ],
            'email' => [
                'sometimes',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->user()->id),
            ],
            'bio' => 'sometimes|nullable|string|max:1000',
            'avatar' => 'sometimes|nullable|string|max:255',
            'website' => 'sometimes|nullable|url|max:255',
            'location' => 'sometimes|nullable|string|max:255',
            'social_links' => 'sometimes|nullable|array',
            'social_links.twitter' => 'sometimes|nullable|url|max:255',
            'social_links.youtube' => 'sometimes|nullable|url|max:255',
            'social_links.instagram' => 'sometimes|nullable|url|max:255',
            'social_links.tiktok' => 'sometimes|nullable|url|max:255',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    #[ArrayShape([
        'username.unique' => "string",
        'website.url' => "string",
        'social_links.twitter.url' => "string",
        'social_links.youtube.url' => "string",
        'social_links.instagram.url' => "string",
        'social_links.tiktok.url' => "string"
    ])] public function messages(): array
    {
        return [
            'username.unique' => 'This username is already taken.',
            'website.url' => 'Please provide a valid website URL.',
            'social_links.twitter.url' => 'Please provide a valid Twitter URL.',
            'social_links.youtube.url' => 'Please provide a valid YouTube URL.',
            'social_links.instagram.url' => 'Please provide a valid Instagram URL.',
            'social_links.tiktok.url' => 'Please provide a valid TikTok URL.',
        ];
    }
}
