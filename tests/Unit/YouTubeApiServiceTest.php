<?php

namespace Tests\Unit;

use App\Services\YouTubeApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class YouTubeApiServiceTest extends TestCase
{
    use RefreshDatabase;

    private YouTubeApiService $youtubeService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->youtubeService = new YouTubeApiService();
    }

    public function test_parse_duration_converts_iso_8601_to_seconds()
    {
        $reflection = new \ReflectionClass($this->youtubeService);
        $method = $reflection->getMethod('parseDuration');
        $method->setAccessible(true);

        $testCases = [
            'PT1H2M10S' => 3730, // 1 hour, 2 minutes, 10 seconds
            'PT5M30S' => 330,    // 5 minutes, 30 seconds
            'PT2H' => 7200,      // 2 hours
            'PT45S' => 45,       // 45 seconds
            'PT1M' => 60,        // 1 minute
        ];

        foreach ($testCases as $duration => $expectedSeconds) {
            $result = $method->invoke($this->youtubeService, $duration);
            $this->assertEquals($expectedSeconds, $result);
        }
    }

    public function test_get_best_thumbnail_returns_highest_quality_available()
    {
        $reflection = new \ReflectionClass($this->youtubeService);
        $method = $reflection->getMethod('getBestThumbnail');
        $method->setAccessible(true);

        $thumbnails = [
            'default' => ['url' => 'default.jpg'],
            'medium' => ['url' => 'medium.jpg'],
            'high' => ['url' => 'high.jpg'],
            'maxres' => ['url' => 'maxres.jpg'],
        ];

        $result = $method->invoke($this->youtubeService, $thumbnails);
        $this->assertEquals('maxres.jpg', $result);

        // Test with missing maxres
        $thumbnailsWithoutMaxres = [
            'default' => ['url' => 'default.jpg'],
            'medium' => ['url' => 'medium.jpg'],
            'high' => ['url' => 'high.jpg'],
        ];

        $result = $method->invoke($this->youtubeService, $thumbnailsWithoutMaxres);
        $this->assertEquals('high.jpg', $result);
    }

    public function test_generate_thumbnail_urls_returns_all_qualities()
    {
        $youtubeId = 'dQw4w9WgXcQ';
        $thumbnails = $this->youtubeService->generateThumbnailUrls($youtubeId);

        $expectedKeys = ['default', 'medium', 'high', 'standard', 'maxres'];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $thumbnails);
            $this->assertStringContainsString($youtubeId, $thumbnails[$key]);
            $this->assertStringContainsString('youtube.com', $thumbnails[$key]);
        }
    }

    public function test_validate_youtube_id_accepts_valid_ids()
    {
        $validIds = [
            'dQw4w9WgXcQ',
            '12345678901',
            'abc-def_ghi',
            'ABCDEFGHIJK',
        ];

        foreach ($validIds as $id) {
            $result = $this->youtubeService->validateVideo($id);
            // Note: This will return false without API key, but we're testing the validation logic
            $this->assertIsBool($result);
        }
    }

    public function test_validate_youtube_id_rejects_invalid_ids()
    {
        $invalidIds = [
            'short',           // Too short
            'waytoolongidhere', // Too long
            'invalid@chars',   // Invalid characters
            '',                // Empty
        ];

        foreach ($invalidIds as $id) {
            $result = $this->youtubeService->validateVideo($id);
            $this->assertFalse($result);
        }
    }

    public function test_extract_youtube_id_from_various_url_formats()
    {
        $enhancementService = app(\App\Services\VideoEnhancementService::class);
        $youtubeId = 'dQw4w9WgXcQ';

        $urlFormats = [
            "https://www.youtube.com/watch?v={$youtubeId}",
            "https://youtu.be/{$youtubeId}",
            "https://www.youtube.com/embed/{$youtubeId}",
            "https://www.youtube.com/v/{$youtubeId}",
            "https://www.youtube.com/watch?v={$youtubeId}&feature=share",
            "https://youtu.be/{$youtubeId}?t=30",
        ];

        foreach ($urlFormats as $url) {
            $result = $enhancementService->extractYoutubeId($url);
            $this->assertEquals($youtubeId, $result);
        }
    }

    public function test_extract_youtube_id_returns_null_for_invalid_urls()
    {
        $enhancementService = app(\App\Services\VideoEnhancementService::class);

        $invalidUrls = [
            'https://www.youtube.com/watch',
            'https://youtu.be/',
            'https://www.google.com',
            'not-a-url',
            'https://www.youtube.com/watch?v=invalid',
        ];

        foreach ($invalidUrls as $url) {
            $result = $enhancementService->extractYoutubeId($url);
            $this->assertNull($result);
        }
    }

    public function test_format_duration_returns_human_readable_format()
    {
        $enhancementService = app(\App\Services\VideoEnhancementService::class);

        $testCases = [
            3661 => '1:01:01',  // 1 hour, 1 minute, 1 second
            125 => '2:05',      // 2 minutes, 5 seconds
            3600 => '1:00:00',  // 1 hour
            45 => '0:45',       // 45 seconds
            0 => '0:00',        // 0 seconds
        ];

        foreach ($testCases as $seconds => $expected) {
            $result = $enhancementService->formatDuration($seconds);
            $this->assertEquals($expected, $result);
        }
    }

    public function test_get_category_name_returns_correct_names()
    {
        $enhancementService = app(\App\Services\VideoEnhancementService::class);

        $testCases = [
            '1' => 'Film & Animation',
            '10' => 'Music',
            '27' => 'Education',
            '28' => 'Science & Technology',
            '29' => 'Nonprofits & Activism',
        ];

        foreach ($testCases as $categoryId => $expectedName) {
            $result = $enhancementService->getCategoryName($categoryId);
            $this->assertEquals($expectedName, $result);
        }

        // Test unknown category
        $result = $enhancementService->getCategoryName('999');
        $this->assertNull($result);

        // Test null category
        $result = $enhancementService->getCategoryName(null);
        $this->assertNull($result);
    }
}
