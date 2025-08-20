<?php

namespace Tests\Feature;

use App\Models\Collection;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CollectionFollowTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_follow_collection()
    {
        $user = User::factory()->create();
        $profile = UserProfile::factory()->create(['user_id' => $user->id]);
        $collection = Collection::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/follows/collections', [
            'collection_id' => $collection->id,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Collection followed successfully',
                'follow' => [
                    'follower_id' => $user->id,
                    'collection_id' => $collection->id,
                ]
            ]);
    }

    public function test_authenticated_user_cannot_follow_collection_twice()
    {
        $user = User::factory()->create();
        $profile = UserProfile::factory()->create(['user_id' => $user->id]);
        $collection = Collection::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        // Follow the collection first time
        $this->postJson('/api/follows/collections', [
            'collection_id' => $collection->id,
        ]);

        // Try to follow again
        $response = $this->postJson('/api/follows/collections', [
            'collection_id' => $collection->id,
        ]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'Already following this collection']);
    }

    public function test_authenticated_user_can_unfollow_collection()
    {
        $user = User::factory()->create();
        $profile = UserProfile::factory()->create(['user_id' => $user->id]);
        $collection = Collection::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        // Follow the collection first
        $this->postJson('/api/follows/collections', [
            'collection_id' => $collection->id,
        ]);

        // Unfollow the collection
        $response = $this->deleteJson('/api/follows/collections', [
            'collection_id' => $collection->id,
        ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Collection unfollowed successfully']);
    }

    public function test_authenticated_user_can_get_followed_collections()
    {
        $user = User::factory()->create();
        $profile = UserProfile::factory()->create(['user_id' => $user->id]);
        $collection = Collection::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        // Follow the collection
        $this->postJson('/api/follows/collections', [
            'collection_id' => $collection->id,
        ]);

        // Get followed collections
        $response = $this->getJson('/api/follows/collections');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'slug',
                        'description',
                        'cover_image',
                        'layout',
                        'is_public',
                        'is_featured',
                        'view_count',
                        'like_count',
                        'video_count',
                        'user',
                        'videos',
                        'followed_at',
                    ]
                ],
                'meta' => [
                    'total',
                    'per_page',
                    'current_page',
                    'last_page',
                ]
            ])
            ->assertJson([
                'data' => [
                    [
                        'id' => $collection->id,
                        'title' => $collection->title,
                    ]
                ]
            ]);
    }

    public function test_authenticated_user_can_check_if_following_collection()
    {
        $user = User::factory()->create();
        $profile = UserProfile::factory()->create(['user_id' => $user->id]);
        $collection = Collection::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        // Check before following
        $response = $this->getJson('/api/follows/collections/check?collection_id=' . $collection->id);

        $response->assertStatus(200)
            ->assertJson(['is_following' => false]);

        // Follow the collection
        $this->postJson('/api/follows/collections', [
            'collection_id' => $collection->id,
        ]);

        // Check after following
        $response = $this->getJson('/api/follows/collections/check?collection_id=' . $collection->id);

        $response->assertStatus(200)
            ->assertJson(['is_following' => true]);
    }

    public function test_unauthenticated_user_cannot_access_collection_follow_endpoints()
    {
        $collection = Collection::factory()->create();

        // Try to follow collection
        $response = $this->postJson('/api/follows/collections', [
            'collection_id' => $collection->id,
        ]);
        $response->assertStatus(401);

        // Try to get followed collections
        $response = $this->getJson('/api/follows/collections');
        $response->assertStatus(401);

        // Try to check if following
        $response = $this->getJson('/api/follows/collections/check?collection_id=' . $collection->id);
        $response->assertStatus(401);
    }
}
