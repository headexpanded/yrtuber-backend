<?php

use App\Http\Controllers\Api\CollectionController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\FollowController;
use App\Http\Controllers\Api\LikeController;
use App\Http\Controllers\Api\RecommendationController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\TrendingController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\VideoController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// User management routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/profile', [UserController::class, 'profile']);
    Route::put('/profile', [UserController::class, 'update']);
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
