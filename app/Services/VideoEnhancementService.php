<?php

namespace App\Services;

use App\Models\Video;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class VideoEnhancementService
{
    private YouTubeApiService $youtubeService;

    public function __construct(YouTubeApiService $youtubeService)
    {
        $this->youtubeService = $youtubeService;
    }

    /**
     * Enhance video with YouTube metadata
     */
    public function enhanceVideo(Video $video): bool
    {
        try {
            $metadata = $this->youtubeService->fetchVideoMetadata($video->youtube_id);

            if (!$metadata) {
                Log::warning('Failed to fetch metadata for video', ['youtube_id' => $video->youtube_id]);
                return false;
            }

            // Update video with enhanced metadata
            $video->update([
                'title' => $metadata['title'],
                'description' => $metadata['description'],
                'channel_name' => $metadata['channel_name'],
                'channel_id' => $metadata['channel_id'],
                'duration' => $metadata['duration'],
                'published_at' => $metadata['published_at'],
                'view_count' => $metadata['view_count'],
                'like_count' => $metadata['like_count'],
                'thumbnail_url' => $metadata['thumbnail_url'],
                'metadata' => array_merge($video->metadata ?? [], $metadata['metadata']),
            ]);

            Log::info('Video enhanced successfully', [
                'youtube_id' => $video->youtube_id,
                'title' => $metadata['title'],
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Error enhancing video', [
                'youtube_id' => $video->youtube_id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Create video from YouTube ID with automatic metadata fetching
     */
    public function createFromYoutubeId(string $youtubeId, array $additionalData = []): ?Video
    {
        // Check if video already exists
        if (Video::where('youtube_id', $youtubeId)->exists()) {
            Log::warning('Video already exists', ['youtube_id' => $youtubeId]);
            return null;
        }

        try {
            $metadata = $this->youtubeService->fetchVideoMetadata($youtubeId);

            if (!$metadata) {
                Log::warning('Failed to fetch metadata for new video', ['youtube_id' => $youtubeId]);
                return null;
            }

            // Merge additional data with fetched metadata
            $videoData = array_merge($metadata, $additionalData);

            $video = Video::create($videoData);

            Log::info('Video created from YouTube ID', [
                'youtube_id' => $youtubeId,
                'title' => $metadata['title'],
            ]);

            return $video;
        } catch (\Exception $e) {
            Log::error('Error creating video from YouTube ID', [
                'youtube_id' => $youtubeId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Update video metadata from YouTube
     */
    public function refreshVideoMetadata(Video $video): bool
    {
        return $this->enhanceVideo($video);
    }

    /**
     * Batch enhance multiple videos
     */
    public function batchEnhanceVideos(array $videoIds): array
    {
        $results = [
            'success' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ($videoIds as $videoId) {
            $video = Video::find($videoId);

            if (!$video) {
                $results['failed']++;
                $results['errors'][] = "Video not found: {$videoId}";
                continue;
            }

            if ($this->enhanceVideo($video)) {
                $results['success']++;
            } else {
                $results['failed']++;
                $results['errors'][] = "Failed to enhance video: {$video->youtube_id}";
            }
        }

        return $results;
    }

    /**
     * Generate enhanced thumbnail URLs
     */
    public function generateEnhancedThumbnails(Video $video): array
    {
        $thumbnails = $this->youtubeService->generateThumbnailUrls($video->youtube_id);

        // Add quality detection
        $thumbnails['quality'] = $this->youtubeService->detectVideoQuality($video->youtube_id);

        return $thumbnails;
    }

    /**
     * Get video quality information
     */
    public function getVideoQualityInfo(Video $video): array
    {
        $metadata = $video->metadata ?? [];

        return [
            'definition' => $metadata['definition'] ?? 'sd',
            'dimension' => $metadata['dimension'] ?? null,
            'projection' => $metadata['projection'] ?? 'rectangular',
            'has_custom_thumbnail' => $metadata['has_custom_thumbnail'] ?? false,
            'caption' => $metadata['caption'] ?? false,
            'licensed_content' => $metadata['licensed_content'] ?? false,
        ];
    }

    /**
     * Validate and clean YouTube ID
     */
    public function validateYoutubeId(string $youtubeId): ?string
    {
        // YouTube video IDs are 11 characters long
        if (strlen($youtubeId) !== 11) {
            return null;
        }

        // YouTube video IDs contain only alphanumeric characters, hyphens, and underscores
        if (!preg_match('/^[a-zA-Z0-9_-]{11}$/', $youtubeId)) {
            return null;
        }

        return $youtubeId;
    }

    /**
     * Extract YouTube ID from various URL formats
     */
    public function extractYoutubeId(string $url): ?string
    {
        $patterns = [
            '/youtube\.com\/watch\?v=([a-zA-Z0-9_-]{11})/',
            '/youtu\.be\/([a-zA-Z0-9_-]{11})/',
            '/youtube\.com\/embed\/([a-zA-Z0-9_-]{11})/',
            '/youtube\.com\/v\/([a-zA-Z0-9_-]{11})/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return $this->validateYoutubeId($matches[1]);
            }
        }

        // If the input is already a valid YouTube ID
        if ($this->validateYoutubeId($url)) {
            return $url;
        }

        return null;
    }

    /**
     * Get video duration in human-readable format
     */
    public function formatDuration(int $seconds): string
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $secs);
        }

        return sprintf('%d:%02d', $minutes, $secs);
    }

    /**
     * Get video category name from category ID
     */
    public function getCategoryName(?string $categoryId): ?string
    {
        if (!$categoryId) {
            return null;
        }

        $categories = [
            '1' => 'Film & Animation',
            '2' => 'Autos & Vehicles',
            '10' => 'Music',
            '15' => 'Pets & Animals',
            '17' => 'Sports',
            '19' => 'Travel & Events',
            '20' => 'Gaming',
            '22' => 'People & Blogs',
            '23' => 'Comedy',
            '24' => 'Entertainment',
            '25' => 'News & Politics',
            '26' => 'Howto & Style',
            '27' => 'Education',
            '28' => 'Science & Technology',
            '29' => 'Nonprofits & Activism',
        ];

        return $categories[$categoryId] ?? null;
    }
}
