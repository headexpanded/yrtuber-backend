<?php

use App\Http\Controllers\Api\ActivityFeedController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CollectionController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\EnhancedVideoController;
use App\Http\Controllers\Api\FollowController;
use App\Http\Controllers\Api\LikeController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\RecommendationController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\SharingController;
use App\Http\Controllers\Api\TrendingController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\VideoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider, and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', [UserController::class, 'profile']);

// Session management routes (authenticated)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/refresh-session', [AuthController::class, 'refreshSession']);
    Route::get('/auth/check', [AuthController::class, 'checkAuth']);
});

// User management routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/profile', [UserController::class, 'profile']);
    Route::put('/profile', [UserController::class, 'update']);
    Route::delete('/user', [UserController::class, 'destroy']);
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{user}', [UserController::class, 'show']);
});

// Public user routes (for viewing profiles)
Route::get('/users/{user}/profile', [UserController::class, 'showPublic']);

// Collection management routes (authenticated)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/collections', [CollectionController::class, 'index']);
    Route::post('/collections', [CollectionController::class, 'store']);
    Route::put('/collections/{collection}', [CollectionController::class, 'update']);
    Route::delete('/collections/{collection}', [CollectionController::class, 'destroy']);
    Route::get('/my-collections', [CollectionController::class, 'myCollections']);
});

// Public collection routes
Route::get('/collections/{collection}', [CollectionController::class, 'show']);
Route::get('/users/{user}/collections', [CollectionController::class, 'userCollections']);

// Video management routes (authenticated)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/videos', [VideoController::class, 'index']);
    Route::post('/videos', [VideoController::class, 'store']);
    Route::put('/videos/{video}', [VideoController::class, 'update']);
    Route::delete('/videos/{video}', [VideoController::class, 'destroy']);
    Route::post('/collections/{collection}/videos', [VideoController::class, 'addToCollection']);
    Route::delete('/collections/{collection}/videos/{video}', [VideoController::class, 'removeFromCollection']);
    Route::put('/collections/{collection}/videos/{video}', [VideoController::class, 'updateInCollection']);
});

// Public video routes
Route::get('/videos/{video}', [VideoController::class, 'show']);
Route::get('/videos/search/youtube', [VideoController::class, 'searchByYoutubeId']);
Route::get('/videos/channel/{channelId}', [VideoController::class, 'byChannel']);

// Like management routes (authenticated)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/likes', [LikeController::class, 'store']);
    Route::delete('/likes', [LikeController::class, 'destroy']);
    Route::get('/likes/check', [LikeController::class, 'check']);
});

// Public like routes
Route::get('/likes', [LikeController::class, 'index']);

// Comment management routes (authenticated)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/comments', [CommentController::class, 'store']);
    Route::put('/comments/{comment}', [CommentController::class, 'update']);
    Route::delete('/comments/{comment}', [CommentController::class, 'destroy']);
});

// Public comment routes
Route::get('/comments', [CommentController::class, 'index']);
Route::get('/comments/{comment}', [CommentController::class, 'show']);

// Follow management routes (authenticated)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/follows', [FollowController::class, 'store']);
    Route::delete('/follows', [FollowController::class, 'destroy']);
    Route::get('/follows/following', [FollowController::class, 'following']);
    Route::get('/follows/followers', [FollowController::class, 'followers']);
    Route::get('/follows/check', [FollowController::class, 'check']);

    // Collection follow management routes
    Route::post('/follows/collections', [FollowController::class, 'followCollection']);
    Route::delete('/follows/collections', [FollowController::class, 'unfollowCollection']);
    Route::get('/follows/collections', [FollowController::class, 'followedCollections']);
    Route::get('/follows/collections/check', [FollowController::class, 'checkCollectionFollow']);
});

// Public follow routes
Route::get('/users/{user}/followers', [FollowController::class, 'userFollowers']);
Route::get('/users/{user}/following', [FollowController::class, 'userFollowing']);

// Search routes (public)
Route::get('/search', [SearchController::class, 'global']);
Route::get('/search/collections', [SearchController::class, 'collections']);
Route::get('/search/videos', [SearchController::class, 'videos']);
Route::get('/search/users', [SearchController::class, 'users']);

// Trending routes (public)
Route::get('/trending/collections', [TrendingController::class, 'collections']);
Route::get('/trending/videos', [TrendingController::class, 'videos']);
Route::get('/trending/creators', [TrendingController::class, 'creators']);
Route::get('/trending/categories', [TrendingController::class, 'categories']);

// Recommendation routes (authenticated)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/recommendations', [RecommendationController::class, 'personalized']);
    Route::get('/recommendations/users', [RecommendationController::class, 'suggestedUsers']);
    Route::get('/recommendations/history', [RecommendationController::class, 'basedOnHistory']);
});

// Public recommendation routes
Route::get('/collections/{collection}/similar', [RecommendationController::class, 'similarCollections']);
Route::get('/videos/{video}/similar', [RecommendationController::class, 'similarVideos']);

// Enhanced video management routes (authenticated)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/enhanced-videos', [EnhancedVideoController::class, 'store']);
    Route::post('/videos/{video}/refresh-metadata', [EnhancedVideoController::class, 'refreshMetadata']);
    Route::post('/videos/batch-refresh-metadata', [EnhancedVideoController::class, 'batchRefreshMetadata']);
    Route::get('/videos/{video}/stats', [EnhancedVideoController::class, 'getVideoStats']);
});

// Public enhanced video routes
Route::get('/enhanced-videos/{video}', [EnhancedVideoController::class, 'show']);
Route::get('/videos/quality/{quality}', [EnhancedVideoController::class, 'getByQuality']);
Route::get('/videos/category/{category_id}', [EnhancedVideoController::class, 'getByCategory']);
Route::post('/youtube/search', [EnhancedVideoController::class, 'searchYouTube']);
Route::post('/youtube/validate', [EnhancedVideoController::class, 'validateYouTube']);
Route::get('/youtube/channel/{channel_id}', [EnhancedVideoController::class, 'getChannelInfo']);

// Social Features - Notifications (authenticated)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
    Route::patch('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy']);
    Route::get('/notifications/stats', [NotificationController::class, 'stats']);
    Route::get('/notifications/sent', [NotificationController::class, 'sent']);
});

// Social Features - Activity Feed (authenticated)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/activity-feed/personalized', [ActivityFeedController::class, 'personalized']);
    Route::get('/activity-feed/user', [ActivityFeedController::class, 'user']);
    Route::get('/activity-feed/targeted', [ActivityFeedController::class, 'targeted']);
    Route::get('/activity-feed/filtered', [ActivityFeedController::class, 'filtered']);
    Route::get('/activity-feed/stats', [ActivityFeedController::class, 'stats']);
});

// Social Features - Activity Feed (public)
Route::get('/activity-feed/global', [ActivityFeedController::class, 'global']);
Route::get('/activity-feed/trending', [ActivityFeedController::class, 'trending']);
Route::get('/users/{username}/activity', [ActivityFeedController::class, 'userPublic']);

// Social Features - Sharing (authenticated)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/collections/{collection}/share', [SharingController::class, 'shareCollection']);
    Route::post('/videos/{video}/share', [SharingController::class, 'shareVideo']);
    Route::get('/collections/{collection}/shares', [SharingController::class, 'collectionShares']);
    Route::get('/shares/user', [SharingController::class, 'userShares']);
    Route::get('/collections/{collection}/shares/analytics', [SharingController::class, 'collectionAnalytics']);
    Route::get('/shares/analytics', [SharingController::class, 'userAnalytics']);
    Route::post('/shares/{shareId}/analytics', [SharingController::class, 'updateAnalytics'])->withoutMiddleware('auth:sanctum');
    Route::delete('/shares/{shareId}', [SharingController::class, 'revokeShare']);
    Route::get('/collections/{collection}/embed', [SharingController::class, 'embedCode']);
});

// Social Features - Sharing (public)
Route::get('/shares/trending', [SharingController::class, 'trending']);
Route::get('/shares/stats', [SharingController::class, 'stats']);
