<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CollectionResource;
use App\Http\Resources\VideoResource;
use App\Models\Collection;
use App\Models\User;
use App\Models\Video;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecommendationController extends Controller
{
    /**
     * Get personalized recommendations for the authenticated user.
     */
    public function personalized(Request $request): JsonResponse
    {
        $request->validate([
            'per_page' => 'sometimes|integer|min:1|max:50',
        ]);

        $user = $request->user();
        $perPage = $request->get('per_page', 15);

        // Get user's interests based on their likes and collections
        $userInterests = $this->getUserInterests($user);

        // Get recommended collections based on user interests
        $recommendedCollections = $this->getRecommendedCollections($user, $userInterests, $perPage);

        // Get recommended videos based on user interests
        $recommendedVideos = $this->getRecommendedVideos($user, $userInterests, $perPage);

        return response()->json([
            'collections' => CollectionResource::collection($recommendedCollections),
            'videos' => VideoResource::collection($recommendedVideos),
            'meta' => [
                'user_interests' => $userInterests,
                'total_collections' => $recommendedCollections->count(),
                'total_videos' => $recommendedVideos->count(),
            ],
        ]);
    }

    /**
     * Get collections similar to a specific collection.
     */
    public function similarCollections(Request $request, Collection $collection): JsonResponse
    {
        $request->validate([
            'per_page' => 'sometimes|integer|min:1|max:50',
        ]);

        $perPage = $request->get('per_page', 15);

        // Get similar collections based on tags and user
        $similarCollections = Collection::query()
            ->where('collections.is_public', true)
            ->where('collections.id', '!=', $collection->id)
            ->where(function ($q) use ($collection) {
                // Same user's collections
                $q->where('collections.user_id', $collection->user_id)
                  // Or collections with similar tags
                  ->orWhereHas('tags', function ($tagQuery) use ($collection) {
                      $tagQuery->whereIn('tags.id', $collection->tags->pluck('id'));
                  });
            })
            ->with(['user.profile', 'tags', 'videos'])
            ->orderBy('collections.view_count', 'desc')
            ->orderBy('collections.like_count', 'desc')
            ->limit($perPage)
            ->get();

        return response()->json([
            'collection_id' => $collection->id,
            'data' => CollectionResource::collection($similarCollections),
            'meta' => [
                'total' => $similarCollections->count(),
            ],
        ]);
    }

    /**
     * Get videos similar to a specific video.
     */
    public function similarVideos(Request $request, Video $video): JsonResponse
    {
        $request->validate([
            'per_page' => 'sometimes|integer|min:1|max:50',
        ]);

        $perPage = $request->get('per_page', 15);

        // Get similar videos based on channel, category, and collections
        $similarVideos = Video::query()
            ->where('id', '!=', $video->id)
            ->where(function ($q) use ($video) {
                // Same channel
                $q->where('channel_id', $video->channel_id)
                  // Or same category
                  ->orWhereJsonContains('metadata->category', $video->metadata['category'] ?? '')
                  // Or videos in the same collections
                  ->orWhereHas('collections', function ($collectionQuery) use ($video) {
                      $collectionQuery->whereIn('id', $video->collections->pluck('id'));
                  });
            })
            ->with(['collections'])
            ->orderBy('view_count', 'desc')
            ->orderBy('like_count', 'desc')
            ->limit($perPage)
            ->get();

        return response()->json([
            'video_id' => $video->id,
            'data' => VideoResource::collection($similarVideos),
            'meta' => [
                'total' => $similarVideos->count(),
            ],
        ]);
    }

    /**
     * Get recommendations for users to follow.
     */
    public function suggestedUsers(Request $request): JsonResponse
    {
        $request->validate([
            'per_page' => 'sometimes|integer|min:1|max:50',
        ]);

        $user = $request->user();
        $perPage = $request->get('per_page', 15);

        // Get users that the current user is not already following
        $suggestedUsers = User::query()
            ->where('id', '!=', $user->id)
            ->whereNotIn('id', $user->follows->pluck('following_id'))
            ->whereHas('collections', function ($q) {
                $q->where('is_public', true);
            })
            ->with(['profile', 'collections' => function ($q) {
                $q->where('is_public', true);
            }])
            ->withCount(['collections' => function ($q) {
                $q->where('is_public', true);
            }])
            ->orderBy('collections_count', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit($perPage)
            ->get();

        return response()->json([
            'data' => \App\Http\Resources\UserResource::collection($suggestedUsers),
            'meta' => [
                'total' => $suggestedUsers->count(),
            ],
        ]);
    }

    /**
     * Get collections based on user's viewing history.
     */
    public function basedOnHistory(Request $request): JsonResponse
    {
        $request->validate([
            'per_page' => 'sometimes|integer|min:1|max:50',
        ]);

        $user = $request->user();
        $perPage = $request->get('per_page', 15);

        // Get collections from users that the current user has viewed collections from
        $viewedUserIds = $user->activityLogs()
            ->where('subject_type', Collection::class)
            ->where('action', 'viewed')
            ->pluck('subject_id');

        $viewedCollections = Collection::whereIn('id', $viewedUserIds)->pluck('user_id');

        $recommendedCollections = Collection::query()
            ->where('is_public', true)
            ->whereIn('user_id', $viewedCollections)
            ->whereNotIn('id', $viewedUserIds)
            ->with(['user.profile', 'tags', 'videos'])
            ->orderBy('view_count', 'desc')
            ->orderBy('like_count', 'desc')
            ->limit($perPage)
            ->get();



        return response()->json([
            'data' => CollectionResource::collection($recommendedCollections),
            'meta' => [
                'total' => $recommendedCollections->count(),
            ],
        ]);
    }

    /**
     * Get user interests based on their activity.
     */
    private function getUserInterests(User $user): array
    {
        $interests = [];

        // Get liked collections and their tags
        $likedCollections = $user->likes()
            ->where('likeable_type', Collection::class)
            ->with('likeable.tags')
            ->get();

        foreach ($likedCollections as $like) {
            if ($like->likeable && $like->likeable->tags) {
                foreach ($like->likeable->tags as $tag) {
                    $interests['tags'][$tag->name] = ($interests['tags'][$tag->name] ?? 0) + 1;
                }
            }
        }

        // Get user's own collections and their tags
        $userCollections = $user->collections()->with('tags')->get();
        foreach ($userCollections as $collection) {
            foreach ($collection->tags as $tag) {
                $interests['tags'][$tag->name] = ($interests['tags'][$tag->name] ?? 0) + 2; // Higher weight for own collections
            }
        }

        // Sort interests by weight
        if (isset($interests['tags'])) {
            arsort($interests['tags']);
            $interests['tags'] = array_slice($interests['tags'], 0, 10, true); // Top 10 interests
        }

        return $interests;
    }

    /**
     * Get recommended collections based on user interests.
     */
    private function getRecommendedCollections(User $user, array $interests, int $perPage)
    {
        $query = Collection::query()
            ->where('is_public', true)
            ->where('user_id', '!=', $user->id)
            ->with(['user.profile', 'tags', 'videos']);

        // Filter by user interests if available
        if (!empty($interests['tags'])) {
            $topTags = array_keys($interests['tags']);
            $query->whereHas('tags', function ($q) use ($topTags) {
                $q->whereIn('name', $topTags);
            });
        }

        return $query->orderBy('view_count', 'desc')
                    ->orderBy('like_count', 'desc')
                    ->limit($perPage)
                    ->get();
    }

    /**
     * Get recommended videos based on user interests.
     */
    private function getRecommendedVideos(User $user, array $interests, int $perPage)
    {
        $query = Video::query()
            ->with(['collections']);

        // Filter by user interests if available
        if (!empty($interests['tags'])) {
            $topTags = array_keys($interests['tags']);
            $query->whereHas('collections.tags', function ($q) use ($topTags) {
                $q->whereIn('name', $topTags);
            });
        }

        return $query->orderBy('view_count', 'desc')
                    ->orderBy('like_count', 'desc')
                    ->limit($perPage)
                    ->get();
    }
}
