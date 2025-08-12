<?php

use App\Http\Controllers\Api\CollectionController;
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
