<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CollectionResource;
use App\Http\Resources\VideoResource;
use App\Models\Collection;
use App\Models\Video;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrendingController extends Controller
{
    /**
     * Get trending collections.
     */
    public function collections(Request $request): JsonResponse
    {
        $request->validate([
            'period' => 'sometimes|string|in:today,week,month,all',
            'per_page' => 'sometimes|integer|min:1|max:50',
            'category' => 'sometimes|string',
        ]);

        $period = $request->get('period', 'all');
        $perPage = $request->get('per_page', 15);
        $category = $request->get('category');

        $collections = Collection::query()
            ->where('is_public', true)
            ->with(['user.profile', 'tags', 'videos']);

        // Apply time period filter
        switch ($period) {
            case 'today':
                $collections->where('collections.created_at', '>=', now()->startOfDay());
                break;
            case 'week':
                $collections->where('collections.created_at', '>=', now()->subWeek());
                break;
            case 'month':
                $collections->where('collections.created_at', '>=', now()->subMonth());
                break;
            // 'all' - no time filter
        }

        // Apply category filter if provided
        if ($category) {
            $collections->whereHas('tags', function ($q) use ($category) {
                $q->where('name', 'like', "%{$category}%");
            });
        }

        // Order by trending score (combination of views, likes, and recency)
        // Use simple ordering that works across all databases
        $collections->orderBy('view_count', 'desc')
                   ->orderBy('like_count', 'desc')
                   ->orderBy('video_count', 'desc')
                   ->orderBy('created_at', 'desc');

        $collections = $collections->paginate($perPage);

        return response()->json([
            'period' => $period,
            'category' => $category,
            'data' => CollectionResource::collection($collections),
            'meta' => [
                'total' => $collections->total(),
                'per_page' => $collections->perPage(),
                'current_page' => $collections->currentPage(),
                'last_page' => $collections->lastPage(),
            ],
        ]);
    }

    /**
     * Get trending videos.
     */
    public function videos(Request $request): JsonResponse
    {
        $request->validate([
            'period' => 'sometimes|string|in:today,week,month,all',
            'per_page' => 'sometimes|integer|min:1|max:50',
            'category' => 'sometimes|string',
            'duration' => 'sometimes|string|in:short,medium,long',
        ]);

        $period = $request->get('period', 'all');
        $perPage = $request->get('per_page', 15);
        $category = $request->get('category');
        $duration = $request->get('duration');

        $videos = Video::query()
            ->with(['collections']);

        // Apply time period filter
        switch ($period) {
            case 'today':
                $videos->where('videos.published_at', '>=', now()->startOfDay());
                break;
            case 'week':
                $videos->where('videos.published_at', '>=', now()->subWeek());
                break;
            case 'month':
                $videos->where('videos.published_at', '>=', now()->subMonth());
                break;
            // 'all' - no time filter
        }

        // Apply duration filter
        if ($duration) {
            switch ($duration) {
                case 'short':
                    $videos->where('videos.duration', '<=', 300); // 5 minutes or less
                    break;
                case 'medium':
                    $videos->whereBetween('videos.duration', [301, 1200]); // 5-20 minutes
                    break;
                case 'long':
                    $videos->where('videos.duration', '>', 1200); // Over 20 minutes
                    break;
            }
        }

        // Apply category filter if provided
        if ($category) {
            $videos->whereJsonContains('videos.metadata->category', $category);
        }

                        // Order by trending score (combination of views, likes, and recency)
        $videos->orderBy('videos.view_count', 'desc')
               ->orderBy('videos.like_count', 'desc');

        $videos = $videos->paginate($perPage);

        return response()->json([
            'period' => $period,
            'category' => $category,
            'duration' => $duration,
            'data' => VideoResource::collection($videos),
            'meta' => [
                'total' => $videos->total(),
                'per_page' => $videos->perPage(),
                'current_page' => $videos->currentPage(),
                'last_page' => $videos->lastPage(),
            ],
        ]);
    }

    /**
     * Get trending creators (users with most popular content).
     */
    public function creators(Request $request): JsonResponse
    {
        $request->validate([
            'period' => 'sometimes|string|in:today,week,month,all',
            'per_page' => 'sometimes|integer|min:1|max:50',
        ]);

        $period = $request->get('period', 'all');
        $perPage = $request->get('per_page', 15);

                    $users = \App\Models\User::query()
            ->whereHas('collections', function ($q) use ($period) {
                $q->where('collections.is_public', true);

                // Apply time period filter
                switch ($period) {
                    case 'today':
                        $q->where('collections.created_at', '>=', now()->startOfDay());
                        break;
                    case 'week':
                        $q->where('collections.created_at', '>=', now()->subWeek());
                        break;
                    case 'month':
                        $q->where('collections.created_at', '>=', now()->subMonth());
                        break;
                }
            })
            ->with(['profile', 'collections' => function ($q) use ($period) {
                $q->where('collections.is_public', true);

                // Apply time period filter
                switch ($period) {
                    case 'today':
                        $q->where('collections.created_at', '>=', now()->startOfDay());
                        break;
                    case 'week':
                        $q->where('collections.created_at', '>=', now()->subWeek());
                        break;
                    case 'month':
                        $q->where('collections.created_at', '>=', now()->subMonth());
                        break;
                }
            }])
            ->withCount(['collections' => function ($q) use ($period) {
                $q->where('collections.is_public', true);

                // Apply time period filter
                switch ($period) {
                    case 'today':
                        $q->where('collections.created_at', '>=', now()->startOfDay());
                        break;
                    case 'week':
                        $q->where('collections.created_at', '>=', now()->subWeek());
                        break;
                    case 'month':
                        $q->where('collections.created_at', '>=', now()->subMonth());
                        break;
                }
            }])
            ->orderBy('collections_count', 'desc')
            ->orderBy('created_at', 'desc');

        $users = $users->paginate($perPage);

        return response()->json([
            'period' => $period,
            'data' => \App\Http\Resources\UserResource::collection($users),
            'meta' => [
                'total' => $users->total(),
                'per_page' => $users->perPage(),
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
            ],
        ]);
    }

    /**
     * Get trending categories/tags.
     */
        public function categories(Request $request): JsonResponse
    {
        $request->validate([
            'period' => 'sometimes|string|in:today,week,month,all',
            'per_page' => 'sometimes|integer|min:1|max:50',
        ]);

        $period = $request->get('period', 'all');
        $perPage = $request->get('per_page', 15);

                        $tags = \App\Models\Tag::query()
                ->whereHas('collections', function ($q) use ($period) {
                    $q->where('collections.is_public', true);

                    // Apply time period filter
                    switch ($period) {
                        case 'today':
                            $q->where('collections.created_at', '>=', now()->startOfDay());
                            break;
                        case 'week':
                            $q->where('collections.created_at', '>=', now()->subWeek());
                            break;
                        case 'month':
                            $q->where('collections.created_at', '>=', now()->subMonth());
                            break;
                    }
                })
                ->withCount(['collections' => function ($q) use ($period) {
                    $q->where('collections.is_public', true);

                    // Apply time period filter
                    switch ($period) {
                        case 'today':
                            $q->where('collections.created_at', '>=', now()->startOfDay());
                            break;
                        case 'week':
                            $q->where('collections.created_at', '>=', now()->subWeek());
                            break;
                        case 'month':
                            $q->where('collections.created_at', '>=', now()->subMonth());
                            break;
                    }
                }])
                ->orderBy('collections_count', 'desc')
                ->orderBy('name', 'asc');

            $tags = $tags->paginate($perPage);

            return response()->json([
                'period' => $period,
                'data' => $tags->map(function ($tag) {
                    return [
                        'id' => $tag->id,
                        'name' => $tag->name,
                        'slug' => $tag->slug,
                        'collections_count' => $tag->collections_count,
                    ];
                }),
                'meta' => [
                    'total' => $tags->total(),
                    'per_page' => $tags->perPage(),
                    'current_page' => $tags->currentPage(),
                    'last_page' => $tags->lastPage(),
                ],
            ]);
    }
}
