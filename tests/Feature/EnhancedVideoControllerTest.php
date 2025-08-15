<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Video;
use App\Services\VideoEnhancementService;
use App\Services\YouTubeApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class EnhancedVideoControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_authenticated_user_can_create_enhanced_video_with_youtube_id()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/enhanced-videos', [
                'youtube_id' => 'dQw4w9WgXcQ',
                'auto_fetch_metadata' => false,
                'title' => 'Test Video',
                'description' => 'Test Description',
                'channel_name' => 'Test Channel',
                'channel_id' => 'UC123456789',
                'duration' => 180,
                'view_count' => 1000,
                'like_count' => 100,
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'video' => [
                    'id',
                    'youtube_id',
                    'title',
                    'description',
                    'channel_name',
                    'duration',
                    'view_count',
                    'like_count',
                ],
            ]);

        $this->assertDatabaseHas('videos', [
            'youtube_id' => 'dQw4w9WgXcQ',
            'title' => 'Test Video',
            'channel_name' => 'Test Channel',
        ]);
    }

    public function test_authenticated_user_can_create_enhanced_video_with_youtube_url()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/enhanced-videos', [
                'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'auto_fetch_metadata' => false,
                'title' => 'Test Video',
                'channel_name' => 'Test Channel',
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('videos', [
            'youtube_id' => 'dQw4w9WgXcQ',
            'title' => 'Test Video',
        ]);
    }

    public function test_enhanced_video_creation_validates_youtube_id_format()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/enhanced-videos', [
                'youtube_id' => 'invalid-id',
                'title' => 'Test Video',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['youtube_id']);
    }

    public function test_enhanced_video_creation_validates_youtube_url_format()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/enhanced-videos', [
                'youtube_url' => 'https://www.google.com',
                'title' => 'Test Video',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['youtube_url']);
    }

    public function test_enhanced_video_creation_requires_either_youtube_id_or_url()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/enhanced-videos', [
                'title' => 'Test Video',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['youtube_id']);
    }

    public function test_authenticated_user_can_refresh_video_metadata()
    {
        $video = Video::factory()->withDefaults()->create([
            'youtube_id' => 'dQw4w9WgXcQ',
            'title' => 'Old Title',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/videos/{$video->id}/refresh-metadata");

        // Since YouTube API is not configured in tests, this should fail gracefully
        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
            ]);
    }

    public function test_public_can_view_enhanced_video_information()
    {
        $video = Video::factory()->withDefaults()->create([
            'youtube_id' => 'dQw4w9WgXcQ',
            'title' => 'Test Video',
            'duration' => 180,
            'metadata' => [
                'definition' => 'hd',
                'category_id' => '27',
                'tags' => ['test', 'video'],
            ],
        ]);

        $response = $this->getJson("/api/enhanced-videos/{$video->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'video' => [
                    'id',
                    'youtube_id',
                    'title',
                    'formatted_duration',
                    'quality_info',
                    'category',
                    'tags',
                    'thumbnails',
                    'embed_url',
                    'watch_url',
                ],
            ]);

        $response->assertJson([
            'video' => [
                'youtube_id' => 'dQw4w9WgXcQ',
                'title' => 'Test Video',
                'formatted_duration' => '3:00',
                'quality_info' => [
                    'definition' => 'hd',
                ],
                'category' => [
                    'id' => '27',
                    'name' => 'Education',
                ],
                'embed_url' => "https://www.youtube.com/embed/{$video->youtube_id}",
                'watch_url' => "https://www.youtube.com/watch?v={$video->youtube_id}",
            ],
        ]);
    }

    public function test_public_can_search_youtube_videos()
    {
        $response = $this->postJson('/api/youtube/search', [
            'query' => 'test video',
            'max_results' => 5,
        ]);

        // Since YouTube API is not configured, this should return empty results
        $response->assertStatus(200)
            ->assertJsonStructure([
                'query',
                'results',
                'count',
            ])
            ->assertJson([
                'query' => 'test video',
                'count' => 0,
            ]);
    }

    public function test_youtube_search_validates_query_parameter()
    {
        $response = $this->postJson('/api/youtube/search', [
            'query' => 'a', // Too short
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['query']);
    }

    public function test_public_can_validate_youtube_url()
    {
        $response = $this->postJson('/api/youtube/validate', [
            'input' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'valid',
                'youtube_id',
                'is_url',
                'exists_on_youtube',
                'message',
            ]);

        $response->assertJson([
            'valid' => true,
            'youtube_id' => 'dQw4w9WgXcQ',
            'is_url' => true,
        ]);
    }

    public function test_public_can_validate_youtube_id()
    {
        $response = $this->postJson('/api/youtube/validate', [
            'input' => 'dQw4w9WgXcQ',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'valid' => true,
                'youtube_id' => 'dQw4w9WgXcQ',
                'is_url' => false,
            ]);
    }

    public function test_youtube_validation_rejects_invalid_input()
    {
        $response = $this->postJson('/api/youtube/validate', [
            'input' => 'invalid-input',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'valid' => false,
                'message' => 'Invalid YouTube URL or ID',
            ]);
    }

    public function test_public_can_get_channel_information()
    {
        $response = $this->getJson('/api/youtube/channel/UC123456789');

        // Since YouTube API is not configured, this should return 404
        $response->assertStatus(404)
            ->assertJsonStructure([
                'message',
            ]);
    }

    public function test_authenticated_user_can_batch_refresh_metadata()
    {
        $videos = Video::factory(3)->withDefaults()->create();

        $response = $this->actingAs($this->user)
            ->postJson('/api/videos/batch-refresh-metadata', [
                'video_ids' => $videos->pluck('id')->toArray(),
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'results' => [
                    'success',
                    'failed',
                    'errors',
                ],
            ]);
    }

    public function test_batch_refresh_validates_video_ids()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/videos/batch-refresh-metadata', [
                'video_ids' => [99999], // Non-existent video
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['video_ids.0']);
    }

    public function test_authenticated_user_can_get_video_statistics()
    {
        $video = Video::factory()->withDefaults()->create([
            'youtube_id' => 'dQw4w9WgXcQ',
            'duration' => 180,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/videos/{$video->id}/stats");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'video_id',
                'youtube_id',
                'stats' => [
                    'total_likes',
                    'total_comments',
                    'collections_count',
                    'public_collections_count',
                    'formatted_duration',
                    'published_ago',
                    'created_ago',
                    'updated_ago',
                ],
            ]);

        $response->assertJson([
            'video_id' => $video->id,
            'youtube_id' => 'dQw4w9WgXcQ',
            'stats' => [
                'formatted_duration' => '3:00',
            ],
        ]);
    }

    public function test_public_can_get_videos_by_quality()
    {
        $hdVideo = Video::factory()->withDefaults()->create([
            'metadata' => ['definition' => 'hd'],
        ]);

        $response = $this->getJson('/api/videos/quality/hd');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta',
            ]);

        $response->assertJsonCount(1, 'data');
        $this->assertEquals($hdVideo->id, $response->json('data.0.id'));
    }

    public function test_public_can_get_videos_by_category()
    {
        $educationVideo = Video::factory()->withDefaults()->create([
            'metadata' => ['category_id' => '27'], // Education
        ]);

        $response = $this->getJson('/api/videos/category/27');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta',
            ]);

        $response->assertJsonCount(1, 'data');
        $this->assertEquals($educationVideo->id, $response->json('data.0.id'));
    }

    public function test_enhanced_video_creation_prevents_duplicates()
    {
        $existingVideo = Video::factory()->withDefaults()->create([
            'youtube_id' => 'dQw4w9WgXcQ',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/enhanced-videos', [
                'youtube_id' => 'dQw4w9WgXcQ',
                'title' => 'Different Title',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['youtube_id']);
    }

    public function test_enhanced_video_creation_with_auto_fetch_metadata()
    {
        // Since YouTube API is not configured, this should fail gracefully
        $response = $this->actingAs($this->user)
            ->postJson('/api/enhanced-videos', [
                'youtube_id' => 'dQw4w9WgXcQ',
                'auto_fetch_metadata' => true,
            ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
            ]);
    }
}
