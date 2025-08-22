<?php

namespace Tests\Feature;

use App\Models\Collection;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CollectionVideosTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_collection_videos_can_be_retrieved(): void
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->create([
            'user_id' => $user->id,
            'is_public' => true,
        ]);

        $videos = Video::factory()->count(3)->create();

        // Attach videos to collection with positions
        $collection->videos()->attach($videos->pluck('id')->toArray(), [
            'position' => 1,
            'curator_notes' => 'Test notes',
        ]);

        $response = $this->getJson("/api/collections/{$collection->id}/videos");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'youtube_id',
                        'title',
                        'description',
                        'thumbnail_url',
                        'channel_name',
                        'channel_id',
                        'duration',
                        'published_at',
                        'view_count',
                        'like_count',
                        'metadata',
                        'created_at',
                        'updated_at',
                        'embed_url',
                        'watch_url',
                    ]
                ]
            ]);

        $this->assertCount(3, $response->json('data'));
    }

    public function test_private_collection_videos_cannot_be_retrieved(): void
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->create([
            'user_id' => $user->id,
            'is_public' => false,
        ]);

        $videos = Video::factory()->count(2)->create();
        $collection->videos()->attach($videos->pluck('id')->toArray());

        $response = $this->getJson("/api/collections/{$collection->id}/videos");

        $response->assertStatus(403)
            ->assertJson(['message' => 'Unauthorized']);
    }

    public function test_private_collection_videos_cannot_be_retrieved_even_by_owner(): void
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->create([
            'user_id' => $user->id,
            'is_public' => false,
        ]);

        $videos = Video::factory()->count(2)->create();
        $collection->videos()->attach($videos->pluck('id')->toArray());

        $response = $this->actingAs($user)
            ->getJson("/api/collections/{$collection->id}/videos");

        $response->assertStatus(403)
            ->assertJson(['message' => 'Unauthorized']);
    }

    public function test_collection_videos_are_ordered_by_position(): void
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->create([
            'user_id' => $user->id,
            'is_public' => true,
        ]);

        $video1 = Video::factory()->create();
        $video2 = Video::factory()->create();
        $video3 = Video::factory()->create();

        // Attach videos with specific positions
        $collection->videos()->attach([
            $video1->id => ['position' => 3, 'curator_notes' => 'Third'],
            $video2->id => ['position' => 1, 'curator_notes' => 'First'],
            $video3->id => ['position' => 2, 'curator_notes' => 'Second'],
        ]);

        $response = $this->getJson("/api/collections/{$collection->id}/videos");

        $response->assertStatus(200);

        $videos = $response->json('data');
        $this->assertCount(3, $videos);

        // Check order: should be by position (1, 2, 3)
        $this->assertEquals($video2->id, $videos[0]['id']);
        $this->assertEquals($video3->id, $videos[1]['id']);
        $this->assertEquals($video1->id, $videos[2]['id']);
    }

    public function test_collection_with_no_videos_returns_empty_array(): void
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->create([
            'user_id' => $user->id,
            'is_public' => true,
        ]);

        $response = $this->getJson("/api/collections/{$collection->id}/videos");

        $response->assertStatus(200)
            ->assertJson(['data' => []]);
    }

    public function test_nonexistent_collection_returns_404(): void
    {
        $response = $this->getJson("/api/collections/99999/videos");

        $response->assertStatus(404);
    }

    public function test_video_resource_excludes_collections_array(): void
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->create([
            'user_id' => $user->id,
            'is_public' => true,
        ]);

        $video = Video::factory()->create();
        $collection->videos()->attach($video->id);

        $response = $this->getJson("/api/collections/{$collection->id}/videos");

        $response->assertStatus(200);

        $videoData = $response->json('data.0');

        // Check that collections array is not present
        $this->assertArrayNotHasKey('collections', $videoData);

        // Check that other expected fields are present
        $this->assertArrayHasKey('id', $videoData);
        $this->assertArrayHasKey('title', $videoData);
        $this->assertArrayHasKey('embed_url', $videoData);
        $this->assertArrayHasKey('watch_url', $videoData);
    }

    public function test_authenticated_user_sees_like_status(): void
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->create([
            'user_id' => $user->id,
            'is_public' => true,
        ]);

        $video = Video::factory()->create();
        $collection->videos()->attach($video->id);

        $response = $this->actingAs($user)
            ->getJson("/api/collections/{$collection->id}/videos");

        $response->assertStatus(200);

        $videoData = $response->json('data.0');
        $this->assertArrayHasKey('is_liked', $videoData);
        $this->assertFalse($videoData['is_liked']); // Should be false since no like exists
    }

    public function test_unauthenticated_user_does_not_see_like_status(): void
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->create([
            'user_id' => $user->id,
            'is_public' => true,
        ]);

        $video = Video::factory()->create();
        $collection->videos()->attach($video->id);

        $response = $this->getJson("/api/collections/{$collection->id}/videos");

        $response->assertStatus(200);

        $videoData = $response->json('data.0');
        $this->assertArrayNotHasKey('is_liked', $videoData);
    }
}
