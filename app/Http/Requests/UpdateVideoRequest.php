<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVideoRequest extends FormRequest
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
        $videoId = $this->route('video')->id ?? null;

        return [
            'youtube_id' => [
                'sometimes',
                'required',
                'string',
                'max:20',
                Rule::unique('videos', 'youtube_id')->ignore($videoId),
            ],
            'title' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|nullable|string|max:5000',
            'thumbnail_url' => 'sometimes|required|url|max:500',
            'channel_name' => 'sometimes|required|string|max:255',
            'channel_id' => 'sometimes|required|string|max:255',
            'duration' => 'sometimes|nullable|integer|min:0',
            'published_at' => 'sometimes|nullable|date',
            'view_count' => 'sometimes|nullable|integer|min:0',
            'like_count' => 'sometimes|nullable|integer|min:0',
            'metadata' => 'sometimes|nullable|array',
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
            'youtube_id.required' => 'YouTube video ID is required.',
            'youtube_id.unique' => 'This YouTube video has already been added.',
            'title.required' => 'Video title is required.',
            'title.max' => 'Video title cannot exceed 255 characters.',
            'description.max' => 'Video description cannot exceed 5000 characters.',
            'thumbnail_url.required' => 'Thumbnail URL is required.',
            'thumbnail_url.url' => 'Please provide a valid thumbnail URL.',
            'channel_name.required' => 'Channel name is required.',
            'channel_id.required' => 'Channel ID is required.',
            'duration.integer' => 'Duration must be a valid number.',
            'duration.min' => 'Duration cannot be negative.',
            'published_at.date' => 'Please provide a valid publication date.',
            'view_count.integer' => 'View count must be a valid number.',
            'view_count.min' => 'View count cannot be negative.',
            'like_count.integer' => 'Like count must be a valid number.',
            'like_count.min' => 'Like count cannot be negative.',
        ];
    }
}
