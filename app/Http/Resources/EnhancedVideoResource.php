<?php

namespace App\Http\Resources;

use App\Services\VideoEnhancementService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EnhancedVideoResource extends JsonResource
{
    private VideoEnhancementService $enhancementService;

    public function __construct($resource)
    {
        parent::__construct($resource);
        try {
            $this->enhancementService = app(VideoEnhancementService::class);
        } catch (\Exception $e) {
            $this->enhancementService = null;
        }
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $metadata = $this->metadata ?? [];

        return [
            'id' => $this->id,
            'youtube_id' => $this->youtube_id,
            'title' => $this->title,
            'description' => $this->description,
            'thumbnail_url' => $this->thumbnail_url,
            'channel_name' => $this->channel_name,
            'channel_id' => $this->channel_id,
            'duration' => $this->duration,
            'formatted_duration' => $this->enhancementService ? $this->enhancementService->formatDuration($this->duration ?? 0) : '0:00',
            'published_at' => $this->published_at?->toISOString(),
            'published_ago' => $this->published_at?->diffForHumans(),
            'view_count' => $this->view_count,
            'like_count' => $this->like_count,

            // Enhanced metadata
            'quality_info' => [
                'definition' => $metadata['definition'] ?? 'sd',
                'dimension' => $metadata['dimension'] ?? null,
                'projection' => $metadata['projection'] ?? 'rectangular',
                'has_custom_thumbnail' => $metadata['has_custom_thumbnail'] ?? false,
                'caption' => $metadata['caption'] ?? false,
                'licensed_content' => $metadata['licensed_content'] ?? false,
            ],

            'category' => [
                'id' => $metadata['category_id'] ?? null,
                'name' => $this->enhancementService ? $this->enhancementService->getCategoryName($metadata['category_id'] ?? null) : null,
            ],

            'tags' => $metadata['tags'] ?? [],
            'languages' => [
                'default' => $metadata['default_language'] ?? null,
                'audio' => $metadata['default_audio_language'] ?? null,
            ],

            'live_broadcast_content' => $metadata['live_broadcast_content'] ?? 'none',
            'content_rating' => $metadata['content_rating'] ?? [],

            // Thumbnail URLs for different qualities
            'thumbnails' => $this->enhancementService ? $this->enhancementService->generateEnhancedThumbnails($this->resource) : [],

            // URLs
            'embed_url' => "https://www.youtube.com/embed/{$this->youtube_id}",
            'watch_url' => "https://www.youtube.com/watch?v={$this->youtube_id}",

            // Relationships
            'collections' => $this->whenLoaded('collections', function () {
                return CollectionResource::collection($this->collections);
            }),

            // Statistics
            'stats' => [
                'total_likes' => $this->when($request->user(), function () {
                    return $this->likes()->count();
                }),
                'total_comments' => $this->when($request->user(), function () {
                    return $this->comments()->count();
                }),
                'collections_count' => $this->collections()->count(),
                'public_collections_count' => $this->collections()->where('is_public', true)->count(),
            ],

            // User interaction
            'is_liked' => $this->when($request->user(), function () use ($request) {
                return $this->likes()->where('user_id', $request->user()->id)->exists();
            }),

            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
