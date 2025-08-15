<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CollectionResource;
use App\Http\Resources\UserResource;
use App\Http\Resources\VideoResource;
use App\Models\Collection;
use App\Models\User;
use App\Models\Video;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    /**
     * Global search across collections, videos, and users.
     */
    public function global(Request $request): JsonResponse
    {
        $request->validate([
            'query' => 'required|string|min:2|max:100',
            'type' => 'sometimes|string|in:collections,videos,users,all',
            'per_page' => 'sometimes|integer|min:1|max:50',
        ]);

        $query = $request->get('query');
        $type = $request->get('type', 'all');
        $perPage = $request->get('per_page', 15);

        $results = [];

        if ($type === 'all' || $type === 'collections') {
            $collections = Collection::query()
                ->where('collections.is_public', true)
                ->where(function ($q) use ($query) {
                    $q->where('collections.title', 'like', "%{$query}%")
                      ->orWhere('collections.description', 'like', "%{$query}%")
                      ->orWhere('collections.slug', 'like', "%{$query}%");
                })
                ->with(['user.profile', 'tags'])
                ->orderBy('collections.view_count', 'desc')
                ->orderBy('collections.like_count', 'desc')
                ->limit($perPage)
                ->get();

            $results['collections'] = CollectionResource::collection($collections);
        }

        if ($type === 'all' || $type === 'videos') {
            $videos = Video::query()
                ->where(function ($q) use ($query) {
                    $q->where('videos.title', 'like', "%{$query}%")
                      ->orWhere('videos.description', 'like', "%{$query}%")
                      ->orWhere('videos.channel_name', 'like', "%{$query}%");
                })
                ->with(['collections'])
                ->orderBy('videos.view_count', 'desc')
                ->orderBy('videos.like_count', 'desc')
                ->limit($perPage)
                ->get();

            $results['videos'] = VideoResource::collection($videos);
        }

        if ($type === 'all' || $type === 'users') {
            $users = User::query()
                ->whereHas('profile', function ($q) use ($query) {
                    $q->where('user_profiles.username', 'like', "%{$query}%")
                      ->orWhere('user_profiles.bio', 'like', "%{$query}%");
                })
                ->orWhere('users.username', 'like', "%{$query}%")
                ->with('profile')
                ->orderBy('users.created_at', 'desc')
                ->limit($perPage)
                ->get();

            $results['users'] = UserResource::collection($users);
        }

        return response()->json([
            'query' => $query,
            'type' => $type,
            'results' => $results,
            'meta' => [
                'total_collections' => isset($results['collections']) ? $results['collections']->count() : 0,
                'total_videos' => isset($results['videos']) ? $results['videos']->count() : 0,
                'total_users' => isset($results['users']) ? $results['users']->count() : 0,
            ],
        ]);

    }

    /**
     * Search collections only.
     */
    public function collections(Request $request): JsonResponse
    {
        $request->validate([
            'query' => 'required|string|min:2|max:100',
            'per_page' => 'sometimes|integer|min:1|max:50',
            'sort' => 'sometimes|string|in:relevance,popular,recent',
        ]);

        $query = $request->get('query');
        $perPage = $request->get('per_page', 15);
        $sort = $request->get('sort', 'relevance');

        $collections = Collection::query()
            ->where('collections.is_public', true)
            ->where(function ($q) use ($query) {
                $q->where('collections.title', 'like', "%{$query}%")
                  ->orWhere('collections.description', 'like', "%{$query}%")
                  ->orWhere('collections.slug', 'like', "%{$query}%");
            })
            ->with(['user.profile', 'tags', 'videos']);

        // Apply sorting
        switch ($sort) {
            case 'popular':
                $collections->orderBy('collections.view_count', 'desc')
                           ->orderBy('collections.like_count', 'desc');
                break;
            case 'recent':
                $collections->orderBy('collections.created_at', 'desc');
                break;
            default: // relevance
                $collections->orderBy('collections.view_count', 'desc')
                           ->orderBy('collections.like_count', 'desc')
                           ->orderBy('collections.created_at', 'desc');
                break;
        }

        $collections = $collections->paginate($perPage);

        return response()->json([
            'query' => $query,
            'sort' => $sort,
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
     * Search videos only.
     */
    public function videos(Request $request): JsonResponse
    {
        $request->validate([
            'query' => 'required|string|min:2|max:100',
            'per_page' => 'sometimes|integer|min:1|max:50',
            'sort' => 'sometimes|string|in:relevance,popular,recent',
            'duration' => 'sometimes|string|in:short,medium,long',
        ]);

        $query = $request->get('query');
        $perPage = $request->get('per_page', 15);
        $sort = $request->get('sort', 'relevance');
        $duration = $request->get('duration');

        $videos = Video::query()
            ->where(function ($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                  ->orWhere('description', 'like', "%{$query}%")
                  ->orWhere('channel_name', 'like', "%{$query}%");
            })
            ->with(['collections']);

        // Apply duration filter
        if ($duration) {
            switch ($duration) {
                case 'short':
                    $videos->where('duration', '<=', 300); // 5 minutes or less
                    break;
                case 'medium':
                    $videos->whereBetween('duration', [301, 1200]); // 5-20 minutes
                    break;
                case 'long':
                    $videos->where('duration', '>', 1200); // Over 20 minutes
                    break;
            }
        }

        // Apply sorting
        switch ($sort) {
            case 'popular':
                $videos->orderBy('view_count', 'desc')
                      ->orderBy('like_count', 'desc');
                break;
            case 'recent':
                $videos->orderBy('published_at', 'desc');
                break;
            default: // relevance
                $videos->orderBy('view_count', 'desc')
                      ->orderBy('like_count', 'desc')
                      ->orderBy('published_at', 'desc');
                break;
        }

        $videos = $videos->paginate($perPage);

        return response()->json([
            'query' => $query,
            'sort' => $sort,
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
     * Search users only.
     */
    public function users(Request $request): JsonResponse
    {
        $request->validate([
            'query' => 'required|string|min:2|max:100',
            'per_page' => 'sometimes|integer|min:1|max:50',
            'sort' => 'sometimes|string|in:relevance,popular,recent',
        ]);

        $query = $request->get('query');
        $perPage = $request->get('per_page', 15);
        $sort = $request->get('sort', 'relevance');

        $users = User::query()
            ->whereHas('profile', function ($q) use ($query) {
                $q->where('username', 'like', "%{$query}%")
                  ->orWhere('bio', 'like', "%{$query}%");
            })
            ->orWhere('username', 'like', "%{$query}%")
            ->with('profile');

        // Apply sorting
        switch ($sort) {
            case 'popular':
                $users->whereHas('profile', function ($q) {
                    $q->orderBy('follower_count', 'desc');
                });
                break;
            case 'recent':
                $users->orderBy('created_at', 'desc');
                break;
            default: // relevance
                $users->orderBy('created_at', 'desc');
                break;
        }

        $users = $users->paginate($perPage);

        return response()->json([
            'query' => $query,
            'sort' => $sort,
            'data' => UserResource::collection($users),
            'meta' => [
                'total' => $users->total(),
                'per_page' => $users->perPage(),
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
            ],
        ]);
    }
}
