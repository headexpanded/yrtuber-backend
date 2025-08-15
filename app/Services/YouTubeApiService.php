<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class YouTubeApiService
{
    private ?string $apiKey;
    private string $baseUrl = 'https://www.googleapis.com/youtube/v3';

    public function __construct()
    {
        $this->apiKey = config('services.youtube.api_key');
    }

    /**
     * Fetch video metadata from YouTube API
     */
    public function fetchVideoMetadata(string $youtubeId): ?array
    {
        if (!$this->apiKey) {
            Log::warning('YouTube API key not configured');
            return null;
        }

        try {
            $response = Http::get("{$this->baseUrl}/videos", [
                'part' => 'snippet,contentDetails,statistics',
                'id' => $youtubeId,
                'key' => $this->apiKey,
            ]);

            if (!$response->successful()) {
                Log::error('YouTube API request failed', [
                    'youtube_id' => $youtubeId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            $data = $response->json();

            if (empty($data['items'])) {
                Log::warning('Video not found on YouTube', ['youtube_id' => $youtubeId]);
                return null;
            }

            $video = $data['items'][0];
            $snippet = $video['snippet'];
            $contentDetails = $video['contentDetails'];
            $statistics = $video['statistics'] ?? [];

            return [
                'title' => $snippet['title'],
                'description' => $snippet['description'],
                'channel_name' => $snippet['channelTitle'],
                'channel_id' => $snippet['channelId'],
                'duration' => $this->parseDuration($contentDetails['duration']),
                'published_at' => $snippet['publishedAt'],
                'view_count' => (int) ($statistics['viewCount'] ?? 0),
                'like_count' => (int) ($statistics['likeCount'] ?? 0),
                'thumbnail_url' => $this->getBestThumbnail($snippet['thumbnails']),
                'metadata' => [
                    'category_id' => $snippet['categoryId'] ?? null,
                    'tags' => $snippet['tags'] ?? [],
                    'default_language' => $snippet['defaultLanguage'] ?? null,
                    'default_audio_language' => $snippet['defaultAudioLanguage'] ?? null,
                    'live_broadcast_content' => $snippet['liveBroadcastContent'] ?? 'none',
                    'content_rating' => $contentDetails['contentRating'] ?? [],
                    'dimension' => $contentDetails['dimension'] ?? null,
                    'definition' => $contentDetails['definition'] ?? 'sd',
                    'caption' => $contentDetails['caption'] ?? false,
                    'licensed_content' => $contentDetails['licensedContent'] ?? false,
                    'projection' => $contentDetails['projection'] ?? 'rectangular',
                    'has_custom_thumbnail' => $contentDetails['hasCustomThumbnail'] ?? false,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Error fetching YouTube video metadata', [
                'youtube_id' => $youtubeId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Parse ISO 8601 duration to seconds
     */
    private function parseDuration(string $duration): int
    {
        $pattern = '/PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?/';
        preg_match($pattern, $duration, $matches);

        $hours = (int) ($matches[1] ?? 0);
        $minutes = (int) ($matches[2] ?? 0);
        $seconds = (int) ($matches[3] ?? 0);

        return ($hours * 3600) + ($minutes * 60) + $seconds;
    }

    /**
     * Get the best quality thumbnail URL
     */
    private function getBestThumbnail(array $thumbnails): string
    {
        // Priority order: maxres, high, medium, standard, default
        $priorities = ['maxres', 'high', 'medium', 'standard', 'default'];

        foreach ($priorities as $quality) {
            if (isset($thumbnails[$quality])) {
                return $thumbnails[$quality]['url'];
            }
        }

        // Fallback to first available thumbnail
        return $thumbnails[array_key_first($thumbnails)]['url'] ?? '';
    }

    /**
     * Generate multiple thumbnail URLs for different qualities
     */
    public function generateThumbnailUrls(string $youtubeId): array
    {
        return [
            'default' => "https://img.youtube.com/vi/{$youtubeId}/default.jpg",
            'medium' => "https://img.youtube.com/vi/{$youtubeId}/mqdefault.jpg",
            'high' => "https://img.youtube.com/vi/{$youtubeId}/hqdefault.jpg",
            'standard' => "https://img.youtube.com/vi/{$youtubeId}/sddefault.jpg",
            'maxres' => "https://img.youtube.com/vi/{$youtubeId}/maxresdefault.jpg",
        ];
    }

    /**
     * Detect video quality based on available formats
     */
    public function detectVideoQuality(string $youtubeId): string
    {
        // This would require additional API calls to get format information
        // For now, we'll use a simple heuristic based on duration and metadata
        return 'hd'; // Placeholder
    }

    /**
     * Search for videos by query
     */
    public function searchVideos(string $query, int $maxResults = 10): array
    {
        if (!$this->apiKey) {
            Log::warning('YouTube API key not configured for search');
            return [];
        }

        try {
            $response = Http::get("{$this->baseUrl}/search", [
                'part' => 'snippet',
                'q' => $query,
                'type' => 'video',
                'maxResults' => $maxResults,
                'key' => $this->apiKey,
            ]);

            if (!$response->successful()) {
                Log::warning('YouTube API search request failed', [
                    'status' => $response->status(),
                    'query' => $query,
                ]);
                return [];
            }

            $data = $response->json();
            return $data['items'] ?? [];
        } catch (\Exception $e) {
            Log::error('Error searching YouTube videos', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Get channel information
     */
    public function getChannelInfo(string $channelId): ?array
    {
        if (!$this->apiKey) {
            return null;
        }

        try {
            $response = Http::get("{$this->baseUrl}/channels", [
                'part' => 'snippet,statistics',
                'id' => $channelId,
                'key' => $this->apiKey,
            ]);

            if (!$response->successful() || empty($response->json('items'))) {
                return null;
            }

            $channel = $response->json('items.0');
            return [
                'name' => $channel['snippet']['title'],
                'description' => $channel['snippet']['description'],
                'thumbnail' => $channel['snippet']['thumbnails']['default']['url'],
                'subscriber_count' => $channel['statistics']['subscriberCount'] ?? 0,
                'video_count' => $channel['statistics']['videoCount'] ?? 0,
                'view_count' => $channel['statistics']['viewCount'] ?? 0,
            ];
        } catch (\Exception $e) {
            Log::error('Error fetching channel info', [
                'channel_id' => $channelId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Check if video exists and is accessible
     */
    public function validateVideo(string $youtubeId): bool
    {
        $metadata = $this->fetchVideoMetadata($youtubeId);
        return $metadata !== null;
    }
}
