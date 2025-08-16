<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
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
            'type' => $this->type,
            'data' => $this->data,
            'read_at' => $this->read_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),

            // Actor information (who triggered the notification)
            'actor' => $this->whenLoaded('actor', function () {
                return [
                    'id' => $this->actor->id,
                    'username' => $this->actor->username,
                    'email' => $this->actor->email,
                    'profile' => $this->actor->profile ? [
                        'avatar' => $this->actor->profile->avatar,
                        'bio' => $this->actor->profile->bio,
                    ] : null,
                ];
            }),

            // Subject information (what the notification is about)
            'subject' => $this->whenLoaded('subject', function () {
                if (!$this->subject) {
                    return null;
                }

                return [
                    'id' => $this->subject->id,
                    'type' => $this->subject_type,
                    'title' => $this->data['subject_title'] ?? null,
                    'url' => $this->getSubjectUrl(),
                ];
            }, null),

            // Additional computed fields
            'is_read' => !is_null($this->read_at),
            'time_ago' => $this->created_at->diffForHumans(),
            'formatted_type' => $this->getFormattedType(),
        ];
    }

    /**
     * Get the subject URL based on type
     */
    private function getSubjectUrl(): ?string
    {
        if (!$this->subject) {
            return null;
        }

        switch ($this->subject_type) {
            case 'App\Models\Collection':
                return url("/collections/{$this->subject->slug}");
            case 'App\Models\Video':
                return url("/videos/{$this->subject->id}");
            case 'App\Models\Comment':
                return url("/comments/{$this->subject->id}");
            default:
                return null;
        }
    }

    /**
     * Get formatted notification type for display
     */
    private function getFormattedType(): string
    {
        $types = [
            'collection_liked' => 'Collection Liked',
            'video_liked' => 'Video Liked',
            'comment_added' => 'Comment Added',
            'user_followed' => 'User Followed',
            'collection_shared' => 'Collection Shared',
        ];

        return $types[$this->type] ?? ucwords(str_replace('_', ' ', $this->type));
    }
}
