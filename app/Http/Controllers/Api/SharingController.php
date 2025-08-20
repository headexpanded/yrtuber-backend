<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ShareCollectionRequest;
use App\Http\Resources\CollectionShareResource;
use App\Models\Collection;
use App\Models\User;
use App\Models\Video;
use App\Services\SharingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;

class SharingController extends Controller
{
    /**
     * @param  SharingService  $sharingService
     */
    public function __construct(
        private SharingService $sharingService
    ) {}

    /**
     * Share a collection
     */
    public function shareCollection(ShareCollectionRequest $request, Collection $collection): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        /** @var User $user */

        // Check if user can view the collection
        if (!$collection->is_public && $collection->user_id !== $user->id) {
            return response()->json(['message' => 'Collection not accessible'], 403);
        }

        $share = $this->sharingService->shareCollection(
            collection: $collection,
            user: $user,
            platform: $request->platform,
            customUrl: $request->get('custom_url'),
            shareType: $request->get('share_type', 'public'),
            expiresAt: $request->get('expires_at')
        );

        return response()->json([
            'message' => 'Collection shared successfully',
            'share' => new CollectionShareResource($share->load(['user.profile', 'collection.user.profile'])),
        ], 201);
    }

    /**
     * Share a video
     */
    public function shareVideo(Request $request, Video $video): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        /** @var User $user */

        // Check if user can view the video (through collections)
        $accessible = $video->collections()
            ->where('is_public', true)
            ->orWhere('user_id', $user->id)
            ->exists();

        if (!$accessible) {
            return response()->json(['message' => 'Video not accessible'], 403);
        }

        $share = $this->sharingService->shareVideo(
            video: $video,
            user: $user,
            platform: $request->platform,
            customUrl: $request->get('custom_url'),
            shareType: $request->get('share_type', 'public'),
            expiresAt: $request->get('expires_at')
        );

        // Load all required relationships
        $share->load(['user.profile', 'collection.user.profile']);

        return response()->json([
            'message' => 'Video shared successfully',
            'share' => new CollectionShareResource($share),
        ], 201);
    }

    /**
     * Get shares for a collection
     */
    public function collectionShares(Collection $collection, Request $request): AnonymousResourceCollection|JsonResponse
    {
        $perPage = $request->get('per_page', 15);

        // Check if user can view the collection
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        /** @var User $user */
        if (!$collection->is_public && $collection->user_id !== $user->id) {
            return response()->json(['message' => 'Collection not accessible'], 403);
        }

        $shares = $this->sharingService->getActiveShares($collection);

        return CollectionShareResource::collection($shares);
    }

    /**
     * Get user's shares
     */
    public function userShares(Request $request): AnonymousResourceCollection|JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        /** @var User $user */
        $perPage = $request->get('per_page', 15);

        $shares = $user->collectionShares()
            ->with(['user.profile', 'collection.user.profile'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return CollectionShareResource::collection($shares);
    }

    /**
     * Get share analytics for a collection
     */
    public function collectionAnalytics(Collection $collection): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        /** @var User $user */

        // Only collection owner can view analytics
        if ($collection->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $analytics = $this->sharingService->getCollectionShareAnalytics($collection);

        return response()->json($analytics);
    }

    /**
     * Get user's share analytics
     */
    public function userAnalytics(): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        /** @var User $user */
        $analytics = $this->sharingService->getUserShareAnalytics($user);

        return response()->json($analytics);
    }

    /**
     * Update share analytics (e.g., when someone clicks a shared link)
     */
    public function updateAnalytics(Request $request, int $shareId): JsonResponse
    {
        $action = $request->get('action', 'click');

        // Find the share
        $share = \App\Models\CollectionShare::findOrFail($shareId);

        // Update analytics
        $this->sharingService->updateShareAnalytics($share, $action);

        return response()->json([
            'message' => 'Analytics updated successfully',
        ]);
    }

    /**
     * Revoke a share
     */
    public function revokeShare(int $shareId): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        /** @var User $user */

        // Find the share
        $share = \App\Models\CollectionShare::findOrFail($shareId);

        // Only the user who created the share or the collection owner can revoke it
        if ($share->user_id !== $user->id && $share->collection->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $this->sharingService->revokeShare($share);

        return response()->json([
            'message' => 'Share revoked successfully',
        ]);
    }

    /**
     * Get share embed code
     */
    public function embedCode(Collection $collection, Request $request): JsonResponse
    {
        $platform = $request->get('platform', 'iframe');

        // Check if user can view the collection
        $user = Auth::user();
        if ($user && !$collection->is_public) {
            /** @var User $user */
            if ($collection->user_id !== $user->id) {
                return response()->json(['message' => 'Collection not accessible'], 403);
            }
        }

        $embedCode = $this->sharingService->getShareEmbedCode($collection, $platform);

        return response()->json([
            'embed_code' => $embedCode,
            'platform' => $platform,
        ]);
    }

    /**
     * Get trending shares
     */
    public function trending(Request $request): AnonymousResourceCollection
    {
        $limit = $request->get('limit', 10);

        $shares = $this->sharingService->getTrendingShares($limit);

        return CollectionShareResource::collection($shares);
    }

    /**
     * Get share statistics summary
     */
    public function stats(): JsonResponse
    {
        $stats = $this->sharingService->getShareStatisticsSummary();

        return response()->json($stats);
    }
}
