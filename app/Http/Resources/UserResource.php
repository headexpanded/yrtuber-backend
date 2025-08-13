<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'username' => $this->username,
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            ...($this->relationLoaded('profile') && $this->profile ? [
                'profile' => [
                    'id' => $this->profile->id,
                    'username' => $this->profile->username,
                    'bio' => $this->profile->bio,
                    'avatar' => $this->profile->avatar,
                    'website' => $this->profile->website,
                    'location' => $this->profile->location,
                    'social_links' => $this->profile->social_links,
                    'is_verified' => $this->profile->is_verified,
                    'is_featured_curator' => $this->profile->is_featured_curator,
                    'follower_count' => $this->profile->follower_count,
                    'following_count' => $this->profile->following_count,
                    'collection_count' => $this->profile->collection_count,
                    'created_at' => $this->profile->created_at,
                    'updated_at' => $this->profile->updated_at,
                ]
            ] : []),
        ];
    }
}
