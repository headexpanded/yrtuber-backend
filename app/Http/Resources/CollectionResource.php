<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CollectionResource extends JsonResource
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
            'user_id' => $this->user_id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'cover_image' => $this->cover_image,
            'layout' => $this->layout,
            'is_public' => $this->is_public,
            'is_featured' => $this->is_featured,
            'view_count' => $this->view_count,
            'like_count' => $this->like_count,
            'video_count' => $this->video_count,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'username' => $this->user->username,
                    'profile' => $this->user->profile ? [
                        'username' => $this->user->profile->username,
                        'avatar' => $this->user->profile->avatar,
                        'is_verified' => $this->user->profile->is_verified,
                    ] : null,
                ];
            }),
            'videos' => $this->whenLoaded('videos', function () {
                return $this->videos->map(function ($video) {
                    return [
                        'id' => $video->id,
                        'youtube_id' => $video->youtube_id,
                        'title' => $video->title,
                        'description' => $video->description,
                        'thumbnail_url' => $video->thumbnail_url,
                        'duration' => $video->duration,
                        'channel_name' => $video->channel_name,
                        'published_at' => $video->published_at,
                        'pivot' => [
                            'position' => $video->pivot->position,
                            'curator_notes' => $video->pivot->curator_notes,
                        ],
                    ];
                });
            }),
            'tags' => $this->whenLoaded('tags', function () {
                return $this->tags->map(function ($tag) {
                    return [
                        'id' => $tag->id,
                        'name' => $tag->name,
                        'color' => $tag->color,
                    ];
                });
            }),
            'is_liked' => $this->when($request->user(), function () use ($request) {
                return $this->likes()->where('user_id', $request->user()->id)->exists();
            }),
        ];
    }
}
