<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityLogResource extends JsonResource
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
            'action' => $this->action,
            'properties' => $this->properties,
            'visibility' => $this->visibility,
            'aggregated_count' => $this->aggregated_count ?? 1,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),

            // User who performed the action
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'username' => $this->user->username,
                    'email' => $this->user->email,
                    'profile' => $this->whenLoaded('user.profile', function () {
                        return [
                            'avatar' => $this->user->profile->avatar,
                            'bio' => $this->user->profile->bio,
                        ];
                    }, null),
                ];
            }, null),

            // Target user (if applicable)
            'target_user' => $this->whenLoaded('targetUser', function () {
                return [
                    'id' => $this->targetUser->id,
                    'username' => $this->targetUser->username,
                    'email' => $this->targetUser->email,
                    'profile' => $this->whenLoaded('targetUser.profile', function () {
                        return [
                            'avatar' => $this->targetUser->profile->avatar,
                            'bio' => $this->targetUser->profile->bio,
                        ];
                    }, null),
                ];
            }, null),

            // Subject of the activity
            'subject' => $this->whenLoaded('subject', function () {
                if (!$this->subject) {
                    return null;
                }

                return [
                    'id' => $this->subject->id,
                    'type' => $this->subject_type,
                    'title' => $this->properties['subject_title'] ?? null,
                    'url' => $this->getSubjectUrl(),
                ];
            }, null),

            // Additional computed fields
            'time_ago' => $this->created_at->diffForHumans(),
            'formatted_action' => $this->getFormattedAction(),
            'is_aggregated' => ($this->aggregated_count ?? 1) > 1,
            'other_users' => $this->properties['other_users'] ?? [],
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
            case 'App\Models\User':
                return url("/users/{$this->subject->username}");
            default:
                return null;
        }
    }

    /**
     * Get formatted action for display
     */
    private function getFormattedAction(): string
    {
        $actions = [
            'collection.created' => 'Created Collection',
            'collection.liked' => 'Liked Collection',
            'collection.shared' => 'Shared Collection',
            'video.added' => 'Added Video',
            'video.liked' => 'Liked Video',
            'comment.added' => 'Added Comment',
            'user.followed' => 'Followed User',
        ];

        return $actions[$this->action] ?? ucwords(str_replace('.', ' ', $this->action));
    }
}
