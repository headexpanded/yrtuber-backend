<?php

namespace App\Services;

use App\Models\Collection;
use App\Models\CollectionShare;
use App\Models\User;
use App\Models\Video;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\URL;
use JetBrains\PhpStorm\ArrayShape;

class SharingService
{
    /**
     * Share a collection
     */
    public function shareCollection(
        Collection $collection,
        User $user,
        string $platform,
        ?string $customUrl = null,
        ?string $shareType = 'public',
        ?string $expiresAt = null
    ): CollectionShare {
        $shareUrl = $customUrl ?? $this->generateShareUrl($collection, $platform);

        return CollectionShare::create([
            'collection_id' => $collection->id,
            'user_id' => $user->id,
            'platform' => $platform,
            'url' => $shareUrl,
            'share_type' => $shareType,
            'shared_at' => now(),
            'expires_at' => $expiresAt ? now()->parse($expiresAt) : null,
            'metadata' => [
                'platform' => $platform,
                'share_type' => $shareType,
                'original_url' => URL::to("/collections/{$collection->slug}"),
            ],
        ]);
    }

    /**
     * Share a video
     */
    public function shareVideo(
        Video $video,
        User $user,
        string $platform,
        ?string $customUrl = null,
        ?string $shareType = 'public',
        ?string $expiresAt = null
    ): CollectionShare {
        // For videos, we'll create a share through their primary collection
        // or create a direct video share if no collection exists
        $collection = $video->collections->first();

        if (!$collection) {
            // Create a temporary collection for the video
            $collection = Collection::create([
                'user_id' => $user->id,
                'title' => "Shared Video: {$video->title}",
                'description' => "Video shared by {$user->username}",
                'is_public' => true,
                'slug' => Str::slug("shared-video-{$video->id}"),
            ]);

            // Add video to collection
            $collection->videos()->attach($video->id, ['position' => 1]);
        }

        return $this->shareCollection($collection, $user, $platform, $customUrl, $shareType, $expiresAt);
    }

    /**
     * Generate share URL for a collection
     */
    private function generateShareUrl(Collection $collection, string $platform): string
    {
        $baseUrl = URL::to("/collections/{$collection->slug}");

        switch ($platform) {
            case 'twitter':
                return "https://twitter.com/intent/tweet?url=" . urlencode($baseUrl) . "&text=" . urlencode($collection->title);
            case 'facebook':
                return "https://www.facebook.com/sharer/sharer.php?u=" . urlencode($baseUrl);
            case 'linkedin':
                return "https://www.linkedin.com/sharing/share-offsite/?url=" . urlencode($baseUrl);
            case 'email':
                return "mailto:?subject=" . urlencode($collection->title) . "&body=" . urlencode("Check out this collection: {$baseUrl}");
            case 'link':
            default:
                return $baseUrl;
        }
    }

    /**
     * Get share analytics for a collection
     */
    #[ArrayShape([
        'total_clicks' => "int|mixed",
        'total_views' => "int|mixed",
        'total_shares' => "int",
        'shares_by_platform' => "array",
        'shares_by_type' => "mixed[]",
        'engagement_rate' => "float|int"
    ])] public function getCollectionShareAnalytics(Collection $collection): array
    {
        $shares = $collection->shares()
            ->whereNotNull('analytics')
            ->get();

        $totalClicks = 0;
        $totalViews = 0;
        $platformStats = [];
        $recentActivity = [];

        foreach ($shares as $share) {
            $analytics = $share->analytics ?? [];

            $totalClicks += $analytics['clicks'] ?? 0;
            $totalViews += $analytics['views'] ?? 0;

            // Platform statistics
            $platform = $share->platform;
            if (!isset($platformStats[$platform])) {
                $platformStats[$platform] = [
                    'clicks' => 0,
                    'views' => 0,
                    'shares' => 0,
                ];
            }

            $platformStats[$platform]['clicks'] += $analytics['clicks'] ?? 0;
            $platformStats[$platform]['views'] += $analytics['views'] ?? 0;
            $platformStats[$platform]['shares']++;

            // Recent activity
            if (isset($analytics['last_click'])) {
                $recentActivity[] = [
                    'type' => 'click',
                    'platform' => $platform,
                    'timestamp' => $analytics['last_click'],
                    'share_id' => $share->id,
                ];
            }

            if (isset($analytics['last_view'])) {
                $recentActivity[] = [
                    'type' => 'view',
                    'platform' => $platform,
                    'timestamp' => $analytics['last_view'],
                    'share_id' => $share->id,
                ];
            }
        }

        // Sort recent activity by timestamp
        usort($recentActivity, function ($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });

        return [
            'total_clicks' => $totalClicks,
            'total_views' => $totalViews,
            'total_shares' => $shares->count(),
            'shares_by_platform' => $platformStats,
            'shares_by_type' => $shares->groupBy('share_type')->map(function($group) { return $group->count(); })->toArray(),
            'engagement_rate' => $totalViews > 0 ? ($totalClicks / $totalViews) * 100 : 0,
        ];
    }

    /**
     * Get share analytics for a user
     */
    #[ArrayShape([
        'total_clicks' => "int|mixed",
        'total_views' => "int|mixed",
        'total_shares' => "int",
        'collections_shared' => "int",
        'shares_by_platform' => "array",
        'shares_by_type' => "mixed[]",
        'engagement_rate' => "float|int",
        'average_clicks_per_share' => "float|int"
    ])] public function getUserShareAnalytics(User $user): array
    {
        $shares = $user->collectionShares()
            ->whereNotNull('analytics')
            ->with('collection')
            ->get();

        $totalClicks = 0;
        $totalViews = 0;
        $totalShares = $shares->count();
        $collectionsShared = $shares->pluck('collection_id')->unique()->count();
        $platformStats = [];

        foreach ($shares as $share) {
            $analytics = $share->analytics ?? [];

            $totalClicks += $analytics['clicks'] ?? 0;
            $totalViews += $analytics['views'] ?? 0;

            $platform = $share->platform;
            if (!isset($platformStats[$platform])) {
                $platformStats[$platform] = [
                    'clicks' => 0,
                    'views' => 0,
                    'shares' => 0,
                ];
            }

            $platformStats[$platform]['clicks'] += $analytics['clicks'] ?? 0;
            $platformStats[$platform]['views'] += $analytics['views'] ?? 0;
            $platformStats[$platform]['shares']++;
        }

        return [
            'total_clicks' => $totalClicks,
            'total_views' => $totalViews,
            'total_shares' => $totalShares,
            'collections_shared' => $collectionsShared,
            'shares_by_platform' => $platformStats,
            'shares_by_type' => $shares->groupBy('share_type')->map(function($group) { return $group->count(); })->toArray(),
            'engagement_rate' => $totalViews > 0 ? ($totalClicks / $totalViews) * 100 : 0,
            'average_clicks_per_share' => $totalShares > 0 ? $totalClicks / $totalShares : 0,
        ];
    }

    /**
     * Update share analytics
     */
    public function updateShareAnalytics(CollectionShare $share, string $action): void
    {
        $share->updateAnalytics($action);
    }

    /**
     * Get active shares for a collection
     */
    public function getActiveShares(Collection $collection): \Illuminate\Database\Eloquent\Collection
    {
        return $collection->shares()
            ->active()
            ->with(['user.profile', 'collection.user.profile'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Revoke a share
     */
    public function revokeShare(CollectionShare $share): bool
    {
        return $share->delete();
    }

    /**
     * Get share embed code
     */
        public function getShareEmbedCode(Collection $collection, string $platform = 'link'): string
    {
        $baseUrl = URL::to("/collections/{$collection->slug}");

        switch ($platform) {
            case 'iframe':
                return "<iframe src=\"{$baseUrl}/embed\" width=\"100%\" height=\"600\" style=\"border: none;\"></iframe>";
            case 'link':
            default:
                return $baseUrl;
        }
    }

    /**
     * Get trending shares
     */
    public function getTrendingShares(int $limit = 10): \Illuminate\Pagination\LengthAwarePaginator
    {
        $shares = CollectionShare::query()
            ->whereNotNull('analytics')
            ->with(['collection.user.profile', 'user.profile'])
            ->get()
            ->map(function ($share) {
                $analytics = $share->analytics ?? [];
                $share->engagement_score = ($analytics['clicks'] ?? 0) + (($analytics['views'] ?? 0) * 0.1);
                return $share;
            })
            ->sortByDesc('engagement_score')
            ->values();

        // Create a custom paginator
        $page = request()->get('page', 1);
        $perPage = $limit;
        $offset = ($page - 1) * $perPage;
        $items = $shares->slice($offset, $perPage);

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $shares->count(),
            $perPage,
            $page,
            ['path' => request()->url()]
        );
    }

    /**
     * Clean up expired shares
     */
    public function cleanupExpiredShares(): int
    {
        return CollectionShare::expired()->delete();
    }

    /**
     * Get share statistics summary
     */
    #[ArrayShape([
        'total_shares' => "mixed",
        'active_shares' => "mixed",
        'expired_shares' => "mixed",
        'shares_by_platform' => "mixed",
        'shares_by_type' => "mixed",
        'total_clicks' => "mixed",
        'total_views' => "mixed",
        'engagement_rate' => "float"
    ])] public function getShareStatisticsSummary(): array
    {
        $totalShares = CollectionShare::count();
        $activeShares = CollectionShare::active()->count();
        $expiredShares = CollectionShare::expired()->count();

        $platformDistribution = CollectionShare::select('platform', DB::raw('count(*) as count'))
            ->groupBy('platform')
            ->pluck('count', 'platform')
            ->toArray();

        return [
            'total_shares' => $totalShares,
            'active_shares' => $activeShares,
            'expired_shares' => $expiredShares,
            'shares_by_platform' => $platformDistribution,
            'shares_by_type' => CollectionShare::select('share_type', DB::raw('count(*) as count'))
                ->groupBy('share_type')
                ->pluck('count', 'share_type')
                ->toArray(),
            'total_clicks' => CollectionShare::whereNotNull('analytics')
                ->get()
                ->sum(function ($share) {
                    return ($share->analytics['clicks'] ?? 0);
                }),
            'total_views' => CollectionShare::select('analytics')
                ->whereNotNull('analytics')
                ->get()
                ->sum(function ($share) {
                    return ($share->analytics['views'] ?? 0);
                }),
            'engagement_rate' => $this->calculateOverallEngagementRate(),
        ];
    }

    /**
     * Calculate overall engagement rate
     */
    private function calculateOverallEngagementRate(): float
    {
        $totalViews = CollectionShare::whereNotNull('analytics')
            ->get()
            ->sum(function ($share) {
                return ($share->analytics['views'] ?? 0);
            });

        $totalClicks = CollectionShare::whereNotNull('analytics')
            ->get()
            ->sum(function ($share) {
                return ($share->analytics['clicks'] ?? 0);
            });

        if ($totalViews === 0) {
            return 0.0;
        }

        return round(($totalClicks / $totalViews) * 100, 2);
    }
}
