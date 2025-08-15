<?php

namespace App\Http\Requests;

use App\Services\VideoEnhancementService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEnhancedVideoRequest extends FormRequest
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
            'youtube_id' => [
                'required_without:youtube_url',
                'string',
                'max:20',
                Rule::unique('videos', 'youtube_id'),
                function ($attribute, $value, $fail) {
                    $enhancementService = app(VideoEnhancementService::class);
                    $validId = $enhancementService->validateYoutubeId($value);
                    if (!$validId) {
                        $fail('The YouTube ID format is invalid.');
                    }
                },
            ],
            'youtube_url' => [
                'required_without:youtube_id',
                'string',
                'url',
                function ($attribute, $value, $fail) {
                    $enhancementService = app(VideoEnhancementService::class);
                    $youtubeId = $enhancementService->extractYoutubeId($value);
                    if (!$youtubeId) {
                        $fail('The YouTube URL format is invalid.');
                    }
                },
            ],
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'channel_name' => 'nullable|string|max:255',
            'channel_id' => 'nullable|string|max:255',
            'duration' => 'nullable|integer|min:0',
            'published_at' => 'nullable|date',
            'view_count' => 'nullable|integer|min:0',
            'like_count' => 'nullable|integer|min:0',
            'thumbnail_url' => 'nullable|url',
            'metadata' => 'nullable|array',
            'auto_fetch_metadata' => 'boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'youtube_id.required_without' => 'Either YouTube ID or YouTube URL is required.',
            'youtube_url.required_without' => 'Either YouTube ID or YouTube URL is required.',
            'youtube_id.unique' => 'This video has already been added.',
            'youtube_url.url' => 'Please provide a valid YouTube URL.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // If youtube_url is provided, extract the YouTube ID
        if ($this->has('youtube_url') && !$this->has('youtube_id')) {
            $enhancementService = app(VideoEnhancementService::class);
            $youtubeId = $enhancementService->extractYoutubeId($this->youtube_url);
            
            if ($youtubeId) {
                $this->merge(['youtube_id' => $youtubeId]);
            }
        }
    }

    /**
     * Get the validated data from the request.
     */
    public function validated($key = null, $default = null): mixed
    {
        $validated = parent::validated($key, $default);

        // Remove youtube_url from validated data as we only need youtube_id
        unset($validated['youtube_url']);

        return $validated;
    }
}
