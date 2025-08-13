<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Video;
use App\Models\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VideoControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_authenticated_user_can_create_video()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $videoData = [
            'youtube_id' => 'test12345678',
            'title' => 'Test Video',
            'description' => 'Test description',
            'thumbnail_url' => 'https://example.com/thumb.jpg',
            'channel_name' => 'Test Channel',
            'channel_id' => 'testchannel123456789',
            'duration' => 300,
            'published_at' => now(),
            'view_count' => 1000,
            'like_count' => 50,
        ];

        $response = $this->postJson('/api/videos', $videoData);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Video created successfully',
            ]);

        $this->assertDatabaseHas('videos', [
            'youtube_id' => 'test12345678',
            'title' => 'Test Video',
            'channel_name' => 'Test Channel',
        ]);
    }

    public function test_video_creation_validates_required_fields()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/videos', [
            'title' => '',
            'youtube_id' => '',
            'thumbnail_url' => 'invalid-url',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'youtube_id', 'thumbnail_url']);
    }

    public function test_video_creation_validates_unique_youtube_id()
    {
        $user = User::factory()->create();
        Video::factory()->create(['youtube_id' => 'test12345678']);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/videos', [
            'youtube_id' => 'test12345678',
            'title' => 'Test Video',
            'thumbnail_url' => 'https://example.com/thumb.jpg',
            'channel_name' => 'Test Channel',
            'channel_id' => 'testchannel123456789',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['youtube_id']);
    }

    public function test_authenticated_user_can_list_videos()
    {
        $user = User::factory()->create();
        Video::factory(5)->create();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/videos');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'youtube_id',
                        'title',
                        'channel_name',
                        'embed_url',
                        'watch_url',
                    ],
                ],
                'meta' => [
                    'total',
                    'per_page',
                    'current_page',
                    'last_page',
                ],
            ]);
    }

    public function test_authenticated_user_can_get_specific_video()
    {
        $user = User::factory()->create();
        $video = Video::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/videos/{$video->id}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $video->id,
                    'youtube_id' => $video->youtube_id,
                    'title' => $video->title,
                ],
            ]);
    }

    public function test_authenticated_user_can_update_video()
    {
        $user = User::factory()->create();
        $video = Video::factory()->create();

        Sanctum::actingAs($user);

        $updateData = [
            'title' => 'Updated Video Title',
            'description' => 'Updated description',
        ];

        $response = $this->putJson("/api/videos/{$video->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Video updated successfully',
            ]);

        $this->assertDatabaseHas('videos', [
            'id' => $video->id,
            'title' => 'Updated Video Title',
            'description' => 'Updated description',
        ]);
    }

    public function test_authenticated_user_can_delete_video()
    {
        $user = User::factory()->create();
        $video = Video::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/videos/{$video->id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Video deleted successfully',
            ]);

        $this->assertDatabaseMissing('videos', ['id' => $video->id]);
    }

    public function test_videos_can_be_searched_by_title()
    {
        $user = User::factory()->create();
        Video::factory()->create(['title' => 'Programming Tutorial']);
        Video::factory()->create(['title' => 'Cooking Video']);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/videos?search=Programming');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_videos_can_be_filtered_by_channel()
    {
        $user = User::factory()->create();
        Video::factory()->create(['channel_name' => 'Tech Channel']);
        Video::factory()->create(['channel_name' => 'Cooking Channel']);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/videos?channel_name=Tech');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_videos_support_pagination()
    {
        $user = User::factory()->create();
        Video::factory(15)->create();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/videos?per_page=5');

        $response->assertStatus(200);
        $this->assertCount(5, $response->json('data'));
        $this->assertEquals(15, $response->json('meta.total'));
    }

    public function test_public_video_can_be_viewed_by_anyone()
    {
        $video = Video::factory()->create();

        $response = $this->getJson("/api/videos/{$video->id}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $video->id,
                    'youtube_id' => $video->youtube_id,
                ],
            ]);
    }

    public function test_video_search_by_youtube_id()
    {
        $video = Video::factory()->create(['youtube_id' => 'test12345678']);

        $response = $this->getJson('/api/videos/search/youtube?youtube_id=test12345678');

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $video->id,
                    'youtube_id' => 'test12345678',
                ],
            ]);
    }

    public function test_video_search_by_youtube_id_returns_404_for_nonexistent()
    {
        $response = $this->getJson('/api/videos/search/youtube?youtube_id=nonexistent');

        $response->assertStatus(404);
    }

    public function test_videos_by_channel_endpoint()
    {
        $channelId = 'testchannel123456789';
        Video::factory(3)->create(['channel_id' => $channelId]);
        Video::factory(2)->create(['channel_id' => 'otherchannel']);

        $response = $this->getJson("/api/videos/channel/{$channelId}");

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data'));
    }

    public function test_authenticated_user_can_add_video_to_collection()
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->create(['user_id' => $user->id]);
        $video = Video::factory()->create();

        Sanctum::actingAs($user);

        $addData = [
            'video_id' => $video->id,
            'position' => 1,
            'curator_notes' => 'Great video!',
        ];

        $response = $this->postJson("/api/collections/{$collection->id}/videos", $addData);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Video added to collection successfully',
            ]);

        $this->assertDatabaseHas('collection_video', [
            'collection_id' => $collection->id,
            'video_id' => $video->id,
            'position' => 1,
            'curator_notes' => 'Great video!',
        ]);
    }

    public function test_user_cannot_add_video_to_other_users_collection()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $collection = Collection::factory()->create(['user_id' => $otherUser->id]);
        $video = Video::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/collections/{$collection->id}/videos", [
            'video_id' => $video->id,
        ]);

        $response->assertStatus(403);
    }

    public function test_cannot_add_duplicate_video_to_collection()
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->create(['user_id' => $user->id]);
        $video = Video::factory()->create();

        $collection->videos()->attach($video->id);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/collections/{$collection->id}/videos", [
            'video_id' => $video->id,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Video is already in this collection',
            ]);
    }

    public function test_authenticated_user_can_remove_video_from_collection()
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->create(['user_id' => $user->id]);
        $video = Video::factory()->create();

        $collection->videos()->attach($video->id);

        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/collections/{$collection->id}/videos/{$video->id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Video removed from collection successfully',
            ]);

        $this->assertDatabaseMissing('collection_video', [
            'collection_id' => $collection->id,
            'video_id' => $video->id,
        ]);
    }

    public function test_authenticated_user_can_update_video_in_collection()
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->create(['user_id' => $user->id]);
        $video = Video::factory()->create();

        $collection->videos()->attach($video->id, ['position' => 1]);

        Sanctum::actingAs($user);

        $updateData = [
            'position' => 2,
            'curator_notes' => 'Updated notes',
        ];

        $response = $this->putJson("/api/collections/{$collection->id}/videos/{$video->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Video updated in collection successfully',
            ]);

        $this->assertDatabaseHas('collection_video', [
            'collection_id' => $collection->id,
            'video_id' => $video->id,
            'position' => 2,
            'curator_notes' => 'Updated notes',
        ]);
    }

    public function test_video_embed_and_watch_urls_are_generated()
    {
        $video = Video::factory()->create(['youtube_id' => 'test12345678']);

        $response = $this->getJson("/api/videos/{$video->id}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'embed_url' => 'https://www.youtube.com/embed/test12345678',
                    'watch_url' => 'https://www.youtube.com/watch?v=test12345678',
                ],
            ]);
    }

    public function test_video_metadata_is_stored_and_retrieved()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $metadata = [
            'category' => 'Education',
            'tags' => ['tutorial', 'programming'],
            'language' => 'en',
            'quality' => '1080p',
        ];

        $videoData = [
            'youtube_id' => 'test12345678',
            'title' => 'Test Video',
            'thumbnail_url' => 'https://example.com/thumb.jpg',
            'channel_name' => 'Test Channel',
            'channel_id' => 'testchannel123456789',
            'metadata' => $metadata,
        ];

        $response = $this->postJson('/api/videos', $videoData);

        $response->assertStatus(201);

        $video = Video::find($response->json('video.id'));
        $this->assertEquals($metadata, $video->metadata);
    }
}
