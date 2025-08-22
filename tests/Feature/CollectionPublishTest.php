<?php

namespace Tests\Feature;

use App\Models\Collection;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CollectionPublishTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_publish_collection_with_videos(): void
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->create([
            'user_id' => $user->id,
            'is_published' => false,
        ]);

        $video = Video::factory()->create();
        $collection->videos()->attach($video->id);

        $response = $this->actingAs($user)
            ->patchJson("/api/collections/{$collection->id}/publish", [
                'is_published' => true,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Collection published successfully',
                'collection' => [
                    'id' => $collection->id,
                    'is_published' => true,
                ]
            ]);

        $this->assertTrue($collection->fresh()->is_published);
    }

    public function test_user_can_unpublish_collection(): void
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->create([
            'user_id' => $user->id,
            'is_published' => true,
        ]);

        $response = $this->actingAs($user)
            ->patchJson("/api/collections/{$collection->id}/publish", [
                'is_published' => false,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Collection unpublished successfully',
                'collection' => [
                    'id' => $collection->id,
                    'is_published' => false,
                ]
            ]);

        $this->assertFalse($collection->fresh()->is_published);
    }

    public function test_user_cannot_publish_collection_without_videos(): void
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->create([
            'user_id' => $user->id,
            'is_published' => false,
        ]);

        $response = $this->actingAs($user)
            ->patchJson("/api/collections/{$collection->id}/publish", [
                'is_published' => true,
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Cannot publish collection without videos',
                'errors' => [
                    'is_published' => ['A collection must have at least one video to be published.']
                ]
            ]);

        $this->assertFalse($collection->fresh()->is_published);
    }

    public function test_user_cannot_publish_other_users_collection(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $collection = Collection::factory()->create([
            'user_id' => $user1->id,
            'is_published' => false,
        ]);

        $response = $this->actingAs($user2)
            ->patchJson("/api/collections/{$collection->id}/publish", [
                'is_published' => true,
            ]);

        $response->assertStatus(403)
            ->assertJson(['message' => 'Unauthorized']);

        $this->assertFalse($collection->fresh()->is_published);
    }

    public function test_publish_requires_authentication(): void
    {
        $collection = Collection::factory()->create([
            'is_published' => false,
        ]);

        $response = $this->patchJson("/api/collections/{$collection->id}/publish", [
            'is_published' => true,
        ]);

        $response->assertStatus(401);
    }

    public function test_publish_requires_valid_boolean(): void
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->create([
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->patchJson("/api/collections/{$collection->id}/publish", [
                'is_published' => 'invalid',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['is_published']);
    }

    public function test_publish_requires_is_published_field(): void
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->create([
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->patchJson("/api/collections/{$collection->id}/publish", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['is_published']);
    }
}
