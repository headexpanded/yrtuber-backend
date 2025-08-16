<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Collection;
use App\Models\Like;
use App\Models\Video;
use App\Services\EventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LikeController extends Controller
{
    /**
     * @param  EventService  $eventService
     */
    public function __construct(
        private EventService $eventService
    ) {}

    /**
     * Like a collection or video.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'likeable_type' => 'required|string|in:App\Models\Collection,App\Models\Video',
            'likeable_id' => 'required|integer',
        ]);

        $user = $request->user();
        $likeableType = $request->likeable_type;
        $likeableId = $request->likeable_id;

        // Check if the likeable model exists
        $likeable = $likeableType::find($likeableId);
        if (!$likeable) {
            return response()->json(['message' => 'Resource not found'], 404);
        }

        // Check if user already liked this resource
        $existingLike = Like::where('user_id', $user->id)
            ->where('likeable_type', $likeableType)
            ->where('likeable_id', $likeableId)
            ->first();

        if ($existingLike) {
            return response()->json(['message' => 'Already liked'], 422);
        }

        // Create the like
        $like = Like::create([
            'user_id' => $user->id,
            'likeable_type' => $likeableType,
            'likeable_id' => $likeableId,
        ]);

        // Update the like count on the likeable model
        $likeable->increment('like_count');

        // Trigger social events
        try {
            if ($likeable instanceof Collection) {
                $this->eventService->handleCollectionLiked($user, $likeable);
            } elseif ($likeable instanceof Video) {
                $this->eventService->handleVideoLiked($user, $likeable);
            }
        } catch (\Exception $e) {
            // Log error but don't fail the request
            \Illuminate\Support\Facades\Log::warning('Failed to trigger like event', [
                'user_id' => $user->id,
                'likeable_type' => $likeableType,
                'likeable_id' => $likeableId,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'message' => 'Liked successfully',
            'like' => [
                'id' => $like->id,
                'user_id' => $like->user_id,
                'likeable_type' => $like->likeable_type,
                'likeable_id' => $like->likeable_id,
                'created_at' => $like->created_at,
            ],
        ], 201);
    }

    /**
     * Unlike a collection or video.
     */
    public function destroy(Request $request): JsonResponse
    {
        $request->validate([
            'likeable_type' => 'required|string|in:App\Models\Collection,App\Models\Video',
            'likeable_id' => 'required|integer',
        ]);

        $user = $request->user();
        $likeableType = $request->likeable_type;
        $likeableId = $request->likeable_id;

        // Find the like
        $like = Like::where('user_id', $user->id)
            ->where('likeable_type', $likeableType)
            ->where('likeable_id', $likeableId)
            ->first();

        if (!$like) {
            return response()->json(['message' => 'Like not found'], 404);
        }

        // Delete the like
        $like->delete();

        // Update the like count on the likeable model
        $likeable = $likeableType::find($likeableId);
        if ($likeable) {
            $likeable->decrement('like_count');
        }

        return response()->json([
            'message' => 'Unliked successfully',
        ]);
    }

    /**
     * Get likes for a collection or video.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'likeable_type' => 'required|string|in:App\Models\Collection,App\Models\Video',
            'likeable_id' => 'required|integer',
        ]);

        $likeableType = $request->likeable_type;
        $likeableId = $request->likeable_id;

        // Check if the likeable model exists
        $likeable = $likeableType::find($likeableId);
        if (!$likeable) {
            return response()->json(['message' => 'Resource not found'], 404);
        }

        $likes = Like::where('likeable_type', $likeableType)
            ->where('likeable_id', $likeableId)
            ->with('user.profile')
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'data' => $likes->map(function ($like) {
                return [
                    'id' => $like->id,
                    'user' => [
                        'id' => $like->user->id,
                        'username' => $like->user->username,
                        'profile' => $like->user->profile ? [
                            'username' => $like->user->profile->username,
                            'avatar' => $like->user->profile->avatar,
                        ] : null,
                    ],
                    'created_at' => $like->created_at,
                ];
            }),
            'meta' => [
                'total' => $likes->total(),
                'per_page' => $likes->perPage(),
                'current_page' => $likes->currentPage(),
                'last_page' => $likes->lastPage(),
            ],
        ]);
    }

    /**
     * Check if the current user has liked a resource.
     */
    public function check(Request $request): JsonResponse
    {
        $request->validate([
            'likeable_type' => 'required|string|in:App\Models\Collection,App\Models\Video',
            'likeable_id' => 'required|integer',
        ]);

        $user = $request->user();
        $likeableType = $request->likeable_type;
        $likeableId = $request->likeable_id;

        $isLiked = Like::where('user_id', $user->id)
            ->where('likeable_type', $likeableType)
            ->where('likeable_id', $likeableId)
            ->exists();

        return response()->json([
            'is_liked' => $isLiked,
        ]);
    }
}
