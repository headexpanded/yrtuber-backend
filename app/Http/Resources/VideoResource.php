<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VideoResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'youtube_id' => $this->youtube_id,
            'title' => $this->title,
            'description' => $this->description,
            'thumbnail_url' => $this->thumbnail_url,
            'channel_name' => $this->channel_name,
            'channel_id' => $this->channel_id,
            'duration' => $this->duration,
            'published_at' => $this->published_at,
            'view_count' => $this->view_count,
            'like_count' => $this->like_count,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'collections' => $this->whenLoaded('collections', function () {
                return $this->collections->map(function ($collection) {
                    return [
                        'id' => $collection->id,
                        'title' => $collection->title,
                        'slug' => $collection->slug,
                        'pivot' => [
                            'position' => $collection->pivot->position,
                            'curator_notes' => $collection->pivot->curator_notes,
                        ],
                    ];
                });
            }),
            'is_liked' => $this->when($request->user(), function () use ($request) {
                return $this->likes()->where('user_id', $request->user()->id)->exists();
            }),
            'embed_url' => $this->when(true, function () {
                return "https://www.youtube.com/embed/{$this->youtube_id}";
            }),
            'watch_url' => $this->when(true, function () {
                return "https://www.youtube.com/watch?v={$this->youtube_id}";
            }),
        ];
    }
}
