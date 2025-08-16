<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use JetBrains\PhpStorm\ArrayShape;

class CollectionShareResource extends JsonResource
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
            'platform' => $this->platform,
            'url' => $this->url,
            'share_type' => $this->share_type,
            'shared_at' => $this->shared_at?->toISOString(),
            'expires_at' => $this->expires_at?->toISOString(),
            'metadata' => $this->metadata,
            'analytics' => $this->analytics,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),

            // User who shared the collection
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'username' => $this->user->username,
                    'email' => $this->user->email,
                    'profile' => $this->user->profile ? [
                        'avatar' => $this->user->profile->avatar,
                        'bio' => $this->user->profile->bio,
                    ] : null,
                ];
            }),

            // Collection being shared
            'collection' => $this->whenLoaded('collection', function () {
                return [
                    'id' => $this->collection->id,
                    'title' => $this->collection->title,
                    'slug' => $this->collection->slug,
                    'description' => $this->collection->description,
                    'cover_image' => $this->collection->cover_image,
                    'is_public' => $this->collection->is_public,
                    'user' => $this->collection->user ? [
                        'id' => $this->collection->user->id,
                        'username' => $this->collection->user->username,
                    ] : null,
                ];
            }, null),

            // Additional computed fields
            'is_expired' => $this->isExpired(),
            'is_active' => !$this->isExpired(),
            'time_ago' => $this->shared_at?->diffForHumans(),
            'formatted_platform' => $this->getFormattedPlatform(),
            'embed_code' => $this->getEmbedCode(),

            // Analytics summary
            'analytics_summary' => $this->getAnalyticsSummary(),
        ];
    }

    /**
     * Get formatted platform name
     */
    private function getFormattedPlatform(): string
    {
        $platforms = [
            'twitter' => 'Twitter',
            'facebook' => 'Facebook',
            'linkedin' => 'LinkedIn',
            'email' => 'Email',
            'link' => 'Direct Link',
        ];

        return $platforms[$this->platform] ?? ucwords(str_replace('_', ' ', $this->platform));
    }

    /**
     * Get embed code for the share
     */
    private function getEmbedCode(): string
    {
        if ($this->platform === 'iframe') {
            $baseUrl = url("/collections/{$this->collection->slug}");
            return "<iframe src=\"{$baseUrl}/embed\" width=\"100%\" height=\"600\" style=\"border: none;\"></iframe>";
        }

        return $this->url;
    }

    /**
     * Get analytics summary
     */
    #[ArrayShape([
        'total_clicks' => "int|mixed",
        'total_views' => "int|mixed",
        'last_click' => "mixed|null",
        'last_view' => "mixed|null",
        'engagement_rate' => "float"
    ])] private function getAnalyticsSummary(): array
    {
        $analytics = $this->analytics ?? [];

        return [
            'total_clicks' => $analytics['clicks'] ?? 0,
            'total_views' => $analytics['views'] ?? 0,
            'last_click' => $analytics['last_click'] ?? null,
            'last_view' => $analytics['last_view'] ?? null,
            'engagement_rate' => $this->calculateEngagementRate(),
        ];
    }

    /**
     * Calculate engagement rate
     */
    private function calculateEngagementRate(): float
    {
        $analytics = $this->analytics ?? [];
        $views = $analytics['views'] ?? 0;
        $clicks = $analytics['clicks'] ?? 0;

        if ($views === 0) {
            return 0.0;
        }

        return round(($clicks / $views) * 100, 2);
    }
}
