<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Follow;
use App\Models\User;
use App\Services\EventService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FollowController extends Controller
{
    /**
     * @param  EventService  $eventService
     */
    public function __construct(
        private EventService $eventService
    ) {}

    /**
     * Follow a user.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'following_id' => 'required|integer|exists:users,id',
        ]);

        $follower = $request->user();
        $followingId = $request->following_id;

        // Prevent self-following
        if ($follower->id === $followingId) {
            return response()->json(['message' => 'Cannot follow yourself'], 422);
        }

        // Check if already following
        $existingFollow = Follow::where('follower_id', $follower->id)
            ->where('following_id', $followingId)
            ->first();

        if ($existingFollow) {
            return response()->json(['message' => 'Already following this user'], 422);
        }

        // Create the follow relationship
        $follow = Follow::create([
            'follower_id' => $follower->id,
            'following_id' => $followingId,
        ]);

        // Update follower counts
        $following = User::find($followingId);
        if ($following && $following->profile) {
            $following->profile->increment('follower_count');
        }

        if ($follower->profile) {
            $follower->profile->increment('following_count');
        }

        // Trigger social events
        try {
            $this->eventService->handleUserFollowed($follower, $following);
        } catch (Exception $e) {
            // Log error but don't fail the request
            Log::warning('Failed to trigger follow event', [
                'follower_id' => $follower->id,
                'following_id' => $followingId,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'message' => 'Followed successfully',
            'follow' => [
                'id' => $follow->id,
                'follower_id' => $follow->follower_id,
                'following_id' => $follow->following_id,
                'created_at' => $follow->created_at,
            ],
        ], 201);
    }

    /**
     * Unfollow a user.
     */
    public function destroy(Request $request): JsonResponse
    {
        $request->validate([
            'following_id' => 'required|integer|exists:users,id',
        ]);

        $follower = $request->user();
        $followingId = $request->following_id;

        // Find the follow relationship
        $follow = Follow::where('follower_id', $follower->id)
            ->where('following_id', $followingId)
            ->first();

        if (!$follow) {
            return response()->json(['message' => 'Follow relationship not found'], 404);
        }

        // Delete the follow relationship
        $follow->delete();

        // Update follower counts
        $following = User::find($followingId);
        if ($following && $following->profile) {
            $following->profile->decrement('follower_count');
        }

        if ($follower->profile) {
            $follower->profile->decrement('following_count');
        }

        return response()->json([
            'message' => 'Unfollowed successfully',
        ]);
    }

    /**
     * Get users that the current user is following.
     */
    public function following(Request $request): JsonResponse
    {
        $user = $request->user();

        $following = Follow::where('follower_id', $user->id)
            ->with('following.profile')
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'data' => $following->map(function ($follow) {
                return [
                    'id' => $follow->following->id,
                    'username' => $follow->following->username,
                    'profile' => $follow->following->profile ? [
                        'username' => $follow->following->profile->username,
                        'avatar' => $follow->following->profile->avatar,
                        'bio' => $follow->following->profile->bio,
                        'is_verified' => $follow->following->profile->is_verified,
                        'follower_count' => $follow->following->profile->follower_count,
                        'following_count' => $follow->following->profile->following_count,
                    ] : null,
                    'followed_at' => $follow->created_at,
                ];
            }),
            'meta' => [
                'total' => $following->total(),
                'per_page' => $following->perPage(),
                'current_page' => $following->currentPage(),
                'last_page' => $following->lastPage(),
            ],
        ]);
    }

    /**
     * Get users that are following the current user.
     */
    public function followers(Request $request): JsonResponse
    {
        $user = $request->user();

        $followers = Follow::where('following_id', $user->id)
            ->with('follower.profile')
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'data' => $followers->map(function ($follow) {
                return [
                    'id' => $follow->follower->id,
                    'username' => $follow->follower->username,
                    'profile' => $follow->follower->profile ? [
                        'username' => $follow->follower->profile->username,
                        'avatar' => $follow->follower->profile->avatar,
                        'bio' => $follow->follower->profile->bio,
                        'is_verified' => $follow->follower->profile->is_verified,
                        'follower_count' => $follow->follower->profile->follower_count,
                        'following_count' => $follow->follower->profile->following_count,
                    ] : null,
                    'followed_at' => $follow->created_at,
                ];
            }),
            'meta' => [
                'total' => $followers->total(),
                'per_page' => $followers->perPage(),
                'current_page' => $followers->currentPage(),
                'last_page' => $followers->lastPage(),
            ],
        ]);
    }

    /**
     * Get followers for a specific user (public endpoint).
     */
    public function userFollowers(Request $request, User $user): JsonResponse
    {
        $followers = Follow::where('following_id', $user->id)
            ->with('follower.profile')
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'data' => $followers->map(function ($follow) {
                return [
                    'id' => $follow->follower->id,
                    'username' => $follow->follower->username,
                    'profile' => $follow->follower->profile ? [
                        'username' => $follow->follower->profile->username,
                        'avatar' => $follow->follower->profile->avatar,
                        'bio' => $follow->follower->profile->bio,
                        'is_verified' => $follow->follower->profile->is_verified,
                        'follower_count' => $follow->follower->profile->follower_count,
                        'following_count' => $follow->follower->profile->following_count,
                    ] : null,
                    'followed_at' => $follow->created_at,
                ];
            }),
            'meta' => [
                'total' => $followers->total(),
                'per_page' => $followers->perPage(),
                'current_page' => $followers->currentPage(),
                'last_page' => $followers->lastPage(),
            ],
        ]);
    }

    /**
     * Get users that a specific user is following (public endpoint).
     */
    public function userFollowing(Request $request, User $user): JsonResponse
    {
        $following = Follow::where('follower_id', $user->id)
            ->with('following.profile')
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'data' => $following->map(function ($follow) {
                return [
                    'id' => $follow->following->id,
                    'username' => $follow->following->username,
                    'profile' => $follow->following->profile ? [
                        'username' => $follow->following->profile->username,
                        'avatar' => $follow->following->profile->avatar,
                        'bio' => $follow->following->profile->bio,
                        'is_verified' => $follow->following->profile->is_verified,
                        'follower_count' => $follow->following->profile->follower_count,
                        'following_count' => $follow->following->profile->following_count,
                    ] : null,
                    'followed_at' => $follow->created_at,
                ];
            }),
            'meta' => [
                'total' => $following->total(),
                'per_page' => $following->perPage(),
                'current_page' => $following->currentPage(),
                'last_page' => $following->lastPage(),
            ],
        ]);
    }

    /**
     * Check if the current user is following another user.
     */
    public function check(Request $request): JsonResponse
    {
        $request->validate([
            'following_id' => 'required|integer|exists:users,id',
        ]);

        $follower = $request->user();
        $followingId = $request->following_id;

        $isFollowing = Follow::where('follower_id', $follower->id)
            ->where('following_id', $followingId)
            ->exists();

        return response()->json([
            'is_following' => $isFollowing,
        ]);
    }
}
