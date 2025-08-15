<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Collection;
use App\Models\Comment;
use App\Models\Video;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    /**
     * Display comments for a collection or video.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'commentable_type' => 'required|string|in:App\Models\Collection,App\Models\Video',
            'commentable_id' => 'required|integer',
        ]);

        $commentableType = $request->commentable_type;
        $commentableId = $request->commentable_id;

        // Check if the commentable model exists
        $commentable = $commentableType::find($commentableId);
        if (!$commentable) {
            return response()->json(['message' => 'Resource not found'], 404);
        }

        $comments = Comment::where('commentable_type', $commentableType)
            ->where('commentable_id', $commentableId)
            ->with('user.profile')
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'data' => $comments->map(function ($comment) {
                return [
                    'id' => $comment->id,
                    'content' => $comment->content,
                    'user' => [
                        'id' => $comment->user->id,
                        'username' => $comment->user->username,
                        'profile' => $comment->user->profile ? [
                            'username' => $comment->user->profile->username,
                            'avatar' => $comment->user->profile->avatar,
                        ] : null,
                    ],
                    'created_at' => $comment->created_at,
                    'updated_at' => $comment->updated_at,
                ];
            }),
            'meta' => [
                'total' => $comments->total(),
                'per_page' => $comments->perPage(),
                'current_page' => $comments->currentPage(),
                'last_page' => $comments->lastPage(),
            ],
        ]);
    }

    /**
     * Store a new comment.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'commentable_type' => 'required|string|in:App\Models\Collection,App\Models\Video',
            'commentable_id' => 'required|integer',
            'content' => 'required|string|max:1000',
        ]);

        $user = $request->user();
        $commentableType = $request->commentable_type;
        $commentableId = $request->commentable_id;

        // Check if the commentable model exists
        $commentable = $commentableType::find($commentableId);
        if (!$commentable) {
            return response()->json(['message' => 'Resource not found'], 404);
        }

        // Create the comment
        $comment = Comment::create([
            'user_id' => $user->id,
            'commentable_type' => $commentableType,
            'commentable_id' => $commentableId,
            'content' => $request->validated('content'),
        ]);

        // Load the user and profile relationships
        $comment->load('user.profile');

        return response()->json([
            'message' => 'Comment created successfully',
            'comment' => [
                'id' => $comment->id,
                'content' => $comment->content,
                'user' => [
                    'id' => $comment->user->id,
                    'username' => $comment->user->username,
                    'profile' => $comment->user->profile ? [
                        'username' => $comment->user->profile->username,
                        'avatar' => $comment->user->profile->avatar,
                    ] : null,
                ],
                'created_at' => $comment->created_at,
                'updated_at' => $comment->updated_at,
            ],
        ], 201);
    }

    /**
     * Display the specified comment.
     */
    public function show(Comment $comment): JsonResponse
    {
        $comment->load('user.profile');

        return response()->json([
            'data' => [
                'id' => $comment->id,
                'content' => $comment->content,
                'user' => [
                    'id' => $comment->user->id,
                    'username' => $comment->user->username,
                    'profile' => $comment->user->profile ? [
                        'username' => $comment->user->profile->username,
                        'avatar' => $comment->user->profile->avatar,
                    ] : null,
                ],
                'commentable_type' => $comment->commentable_type,
                'commentable_id' => $comment->commentable_id,
                'created_at' => $comment->created_at,
                'updated_at' => $comment->updated_at,
            ],
        ]);
    }

    /**
     * Update the specified comment.
     */
    public function update(Request $request, Comment $comment): JsonResponse
    {
        // Check if user owns the comment
        if ($comment->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'content' => 'required|string|max:1000',
        ]);

        $comment->update([
            'content' => $request->validated('content'),
        ]);

        // Refresh the model and load relationships
        $comment->refresh();
        $comment->load('user.profile');

        return response()->json([
            'message' => 'Comment updated successfully',
            'comment' => [
                'id' => $comment->id,
                'content' => $comment->content,
                'user' => [
                    'id' => $comment->user->id,
                    'username' => $comment->user->username,
                    'profile' => $comment->user->profile ? [
                        'username' => $comment->user->profile->username,
                        'avatar' => $comment->user->profile->avatar,
                    ] : null,
                ],
                'created_at' => $comment->created_at,
                'updated_at' => $comment->updated_at,
            ],
        ]);
    }

    /**
     * Remove the specified comment.
     */
    public function destroy(Request $request, Comment $comment): JsonResponse
    {
        // Check if user owns the comment
        if ($comment->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $comment->delete();

        return response()->json([
            'message' => 'Comment deleted successfully',
        ]);
    }
}
