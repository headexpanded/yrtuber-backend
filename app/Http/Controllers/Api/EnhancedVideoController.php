<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEnhancedVideoRequest;
use App\Http\Resources\VideoCollection;
use App\Http\Resources\VideoResource;
use App\Models\Video;
use App\Services\VideoEnhancementService;
use App\Services\YouTubeApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EnhancedVideoController extends Controller
{
    private VideoEnhancementService $enhancementService;
    private YouTubeApiService $youtubeService;

    public function __construct(
        VideoEnhancementService $enhancementService,
        YouTubeApiService $youtubeService
    ) {
        $this->enhancementService = $enhancementService;
        $this->youtubeService = $youtubeService;
    }

    /**
     * Create a video with automatic metadata fetching
     */
    public function store(StoreEnhancedVideoRequest $request): JsonResponse
    {
        $data = $request->validated();
        $autoFetch = $data['auto_fetch_metadata'] ?? true;

        try {
            if ($autoFetch) {
                // Create video with automatic metadata fetching
                $video = $this->enhancementService->createFromYoutubeId(
                    $data['youtube_id'],
                    array_filter($data, fn($key) => $key !== 'youtube_id' && $key !== 'auto_fetch_metadata', ARRAY_FILTER_USE_KEY)
                );

                if (!$video) {
                    return response()->json([
                        'message' => 'Failed to create video. YouTube API may not be configured or the video may not exist.',
                    ], 422);
                }
            } else {
                // Create video with provided data only
                $video = Video::create(array_filter($data, fn($key) => $key !== 'auto_fetch_metadata', ARRAY_FILTER_USE_KEY));
            }

            $video->load(['collections']);

            return response()->json([
                'message' => 'Video created successfully',
                'video' => new \App\Http\Resources\EnhancedVideoResource($video),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error creating video',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Refresh video metadata from YouTube
     */
    public function refreshMetadata(Video $video): JsonResponse
    {
        try {
            $success = $this->enhancementService->refreshVideoMetadata($video);

            if ($success) {
                $video->refresh();
                return response()->json([
                    'message' => 'Video metadata refreshed successfully',
                    'video' => new \App\Http\Resources\EnhancedVideoResource($video),
                ]);
            } else {
                return response()->json([
                    'message' => 'Failed to refresh video metadata. YouTube API may not be configured.',
                ], 422);
            }
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error refreshing video metadata',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get enhanced video information
     */
    public function show(Video $video): JsonResponse
    {
        $video->load(['collections']);

        $enhancedData = [
            'video' => new \App\Http\Resources\EnhancedVideoResource($video),
            'thumbnails' => $this->enhancementService ? $this->enhancementService->generateEnhancedThumbnails($video) : [],
            'quality_info' => $this->enhancementService ? $this->enhancementService->getVideoQualityInfo($video) : [],
            'formatted_duration' => $this->enhancementService ? $this->enhancementService->formatDuration($video->duration ?? 0) : '0:00',
            'category_name' => $this->enhancementService ? $this->enhancementService->getCategoryName($video->metadata['category_id'] ?? null) : null,
            'embed_url' => "https://www.youtube.com/embed/{$video->youtube_id}",
            'watch_url' => "https://www.youtube.com/watch?v={$video->youtube_id}",
        ];

        return response()->json($enhancedData);
    }

    /**
     * Search YouTube videos
     */
    public function searchYouTube(Request $request): JsonResponse
    {
        $request->validate([
            'query' => 'required|string|min:2|max:100',
            'max_results' => 'integer|min:1|max:50',
        ]);

        try {
            $results = $this->youtubeService->searchVideos(
                $request->query,
                $request->max_results ?? 10
            );

            return response()->json([
                'query' => $request->query,
                'results' => $results,
                'count' => count($results),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error searching YouTube videos',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Validate YouTube URL or ID
     */
    public function validateYouTube(Request $request): JsonResponse
    {
        $request->validate([
            'input' => 'required|string',
        ]);

        $input = $request->input;
        $youtubeId = null;
        $isUrl = false;

        // Check if it's a URL
        if (filter_var($input, FILTER_VALIDATE_URL)) {
            $youtubeId = $this->enhancementService->extractYoutubeId($input);
            $isUrl = true;
        } else {
            $youtubeId = $this->enhancementService->validateYoutubeId($input);
        }

        if (!$youtubeId) {
            return response()->json([
                'valid' => false,
                'message' => 'Invalid YouTube URL or ID',
            ]);
        }

        // Check if video exists on YouTube
        $exists = $this->youtubeService->validateVideo($youtubeId);

        return response()->json([
            'valid' => true,
            'youtube_id' => $youtubeId,
            'is_url' => $isUrl,
            'exists_on_youtube' => $exists,
            'message' => $exists ? 'Valid YouTube video' : 'Video not found on YouTube',
        ]);
    }

    /**
     * Get channel information
     */
    public function getChannelInfo(Request $request, string $channelId): JsonResponse
    {
        try {
            $channelInfo = $this->youtubeService->getChannelInfo($channelId);

            if (!$channelInfo) {
                return response()->json([
                    'message' => 'Channel not found',
                ], 404);
            }

            return response()->json([
                'channel' => $channelInfo,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error fetching channel information',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Batch refresh metadata for multiple videos
     */
    public function batchRefreshMetadata(Request $request): JsonResponse
    {
        $request->validate([
            'video_ids' => 'required|array|min:1|max:50',
            'video_ids.*' => 'integer|exists:videos,id',
        ]);

        try {
            $results = $this->enhancementService->batchEnhanceVideos($request->video_ids);

            return response()->json([
                'message' => 'Batch refresh completed',
                'results' => $results,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error during batch refresh',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get video statistics
     */
    public function getVideoStats(Video $video): JsonResponse
    {
        $stats = [
            'total_likes' => $video->likes()->count(),
            'total_comments' => $video->comments()->count(),
            'collections_count' => $video->collections()->count(),
            'public_collections_count' => $video->collections()->where('is_public', true)->count(),
            'formatted_duration' => $this->enhancementService ? $this->enhancementService->formatDuration($video->duration ?? 0) : '0:00',
            'published_ago' => $video->published_at ? $video->published_at->diffForHumans() : null,
            'created_ago' => $video->created_at->diffForHumans(),
            'updated_ago' => $video->updated_at->diffForHumans(),
        ];

        return response()->json([
            'video_id' => $video->id,
            'youtube_id' => $video->youtube_id,
            'stats' => $stats,
        ]);
    }

    /**
     * Get videos by quality
     */
    public function getByQuality(Request $request, string $quality): VideoCollection
    {
        if (!in_array($quality, ['sd', 'hd', '4k'])) {
            abort(422, 'Invalid quality parameter. Must be sd, hd, or 4k.');
        }

        $videos = Video::whereJsonContains('metadata->definition', $quality)
            ->with(['collections'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 15);

        return new VideoCollection($videos);
    }

    /**
     * Get videos by category
     */
    public function getByCategory(Request $request, string $categoryId): VideoCollection
    {
        $videos = Video::whereJsonContains('metadata->category_id', $categoryId)
            ->with(['collections'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 15);

        return new VideoCollection($videos);
    }
}
