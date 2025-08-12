<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AddVideoToCollectionRequest;
use App\Http\Requests\StoreVideoRequest;
use App\Http\Requests\UpdateVideoRequest;
use App\Http\Resources\VideoCollection;
use App\Http\Resources\VideoResource;
use App\Models\Collection;
use App\Models\Video;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VideoController extends Controller
{
    /**
     * Display a listing of videos.
     */
    public function index(Request $request): VideoCollection
    {
        $videos = Video::with(['collections'])
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%")
                      ->orWhere('channel_name', 'like', "%{$search}%");
                });
            })
            ->when($request->channel_id, function ($query, $channelId) {
                $query->where('channel_id', $channelId);
            })
            ->when($request->channel_name, function ($query, $channelName) {
                $query->where('channel_name', 'like', "%{$channelName}%");
            })
            ->when($request->min_duration, function ($query, $minDuration) {
                $query->where('duration', '>=', $minDuration);
            })
            ->when($request->max_duration, function ($query, $maxDuration) {
                $query->where('duration', '<=', $maxDuration);
            })
            ->when($request->published_after, function ($query, $date) {
                $query->where('published_at', '>=', $date);
            })
            ->when($request->published_before, function ($query, $date) {
                $query->where('published_at', '<=', $date);
            })
            ->orderBy($request->sort_by ?? 'created_at', $request->sort_order ?? 'desc')
            ->paginate($request->per_page ?? 15);

        return new VideoCollection($videos);
    }

    /**
     * Store a newly created video.
     */
    public function store(StoreVideoRequest $request): JsonResponse
    {
        $data = $request->validated();

        $video = Video::create($data);

        $video->load(['collections']);

        return response()->json([
            'message' => 'Video created successfully',
            'video' => new VideoResource($video),
        ], 201);
    }

    /**
     * Display the specified video.
     */
    public function show(Video $video): VideoResource
    {
        $video->load(['collections']);
        return new VideoResource($video);
    }

    /**
     * Update the specified video.
     */
    public function update(UpdateVideoRequest $request, Video $video): JsonResponse
    {
        $data = $request->validated();
        $video->update($data);

        $video->load(['collections']);

        return response()->json([
            'message' => 'Video updated successfully',
            'video' => new VideoResource($video),
        ]);
    }

    /**
     * Remove the specified video.
     */
    public function destroy(Video $video): JsonResponse
    {
        $video->delete();

        return response()->json([
            'message' => 'Video deleted successfully',
        ]);
    }

    /**
     * Add a video to a collection.
     */
    public function addToCollection(AddVideoToCollectionRequest $request, Collection $collection): JsonResponse
    {
        // Check if user owns the collection
        if ($collection->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $data = $request->validated();

        // Check if video is already in collection
        if ($collection->videos()->where('video_id', $data['video_id'])->exists()) {
            return response()->json(['message' => 'Video is already in this collection'], 422);
        }

        // Get the next position if not specified
        if (!isset($data['position'])) {
            $data['position'] = $collection->videos()->max('position') + 1;
        }

        $collection->videos()->attach($data['video_id'], [
            'position' => $data['position'],
            'curator_notes' => $data['curator_notes'] ?? null,
        ]);

        // Update collection video count
        $collection->update(['video_count' => $collection->videos()->count()]);

        return response()->json([
            'message' => 'Video added to collection successfully',
        ]);
    }

    /**
     * Remove a video from a collection.
     */
    public function removeFromCollection(Request $request, Collection $collection, Video $video): JsonResponse
    {
        // Check if user owns the collection
        if ($collection->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $collection->videos()->detach($video->id);

        // Update collection video count
        $collection->update(['video_count' => $collection->videos()->count()]);

        return response()->json([
            'message' => 'Video removed from collection successfully',
        ]);
    }

    /**
     * Update video position and notes in a collection.
     */
    public function updateInCollection(Request $request, Collection $collection, Video $video): JsonResponse
    {
        // Check if user owns the collection
        if ($collection->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'position' => 'nullable|integer|min:0',
            'curator_notes' => 'nullable|string|max:1000',
        ]);

        $collection->videos()->updateExistingPivot($video->id, [
            'position' => $request->position,
            'curator_notes' => $request->curator_notes,
        ]);

        return response()->json([
            'message' => 'Video updated in collection successfully',
        ]);
    }

    /**
     * Search videos by YouTube ID.
     */
    public function searchByYoutubeId(Request $request): JsonResponse
    {
        $request->validate([
            'youtube_id' => 'required|string|max:20',
        ]);

        $video = Video::where('youtube_id', $request->youtube_id)->first();

        if (!$video) {
            return response()->json(['message' => 'Video not found'], 404);
        }

        return response()->json([
            'video' => new VideoResource($video),
        ]);
    }

    /**
     * Get videos by channel.
     */
    public function byChannel(Request $request, string $channelId): VideoCollection
    {
        $videos = Video::where('channel_id', $channelId)
            ->with(['collections'])
            ->orderBy($request->sort_by ?? 'published_at', $request->sort_order ?? 'desc')
            ->paginate($request->per_page ?? 15);

        return new VideoCollection($videos);
    }
}
