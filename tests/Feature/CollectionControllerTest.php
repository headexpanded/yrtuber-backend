<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Collection;
use App\Models\Video;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CollectionControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_authenticated_user_can_create_collection()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $collectionData = [
            'title' => 'My Test Collection',
            'description' => 'A test collection',
            'layout' => 'grid',
            'is_public' => true,
        ];

        $response = $this->postJson('/api/collections', $collectionData);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Collection created successfully',
            ]);

        $this->assertDatabaseHas('collections', [
            'user_id' => $user->id,
            'title' => 'My Test Collection',
            'description' => 'A test collection',
            'layout' => 'grid',
            'is_public' => true,
        ]);
    }

    public function test_collection_creation_validates_required_fields()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/collections', [
            'title' => '',
            'layout' => 'invalid-layout',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'layout']);
    }

    public function test_collection_creation_validates_unique_slug()
    {
        $user = User::factory()->create();
        Collection::factory()->create(['slug' => 'test-collection']);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/collections', [
            'title' => 'Test Collection',
            'slug' => 'test-collection',
            'layout' => 'grid',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_authenticated_user_can_list_their_collections()
    {
        $user = User::factory()->create();
        Collection::factory(3)->create(['user_id' => $user->id]);
        Collection::factory(2)->create(); // Other users' collections

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/my-collections');

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data'));
    }

    public function test_authenticated_user_can_get_my_collections()
    {
        $user = User::factory()->create();
        Collection::factory(3)->create(['user_id' => $user->id]);
        Collection::factory(2)->create(); // Other users' collections

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/my-collections');

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data'));
    }

    public function test_authenticated_user_can_update_their_collection()
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $updateData = [
            'title' => 'Updated Collection Title',
            'description' => 'Updated description',
            'is_public' => false,
        ];

        $response = $this->putJson("/api/collections/{$collection->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Collection updated successfully',
            ]);

        $this->assertDatabaseHas('collections', [
            'id' => $collection->id,
            'title' => 'Updated Collection Title',
            'description' => 'Updated description',
            'is_public' => false,
        ]);
    }

    public function test_user_cannot_update_other_users_collection()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $collection = Collection::factory()->create(['user_id' => $otherUser->id]);

        Sanctum::actingAs($user);

        $response = $this->putJson("/api/collections/{$collection->id}", [
            'title' => 'Unauthorized Update',
        ]);

        $response->assertStatus(403);
    }

    public function test_authenticated_user_can_delete_their_collection()
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/collections/{$collection->id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Collection deleted successfully',
            ]);

        $this->assertDatabaseMissing('collections', ['id' => $collection->id]);
    }

    public function test_user_cannot_delete_other_users_collection()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $collection = Collection::factory()->create(['user_id' => $otherUser->id]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/collections/{$collection->id}");

        $response->assertStatus(403);
    }

    public function test_public_collection_can_be_viewed_by_anyone()
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->create([
            'user_id' => $user->id,
            'is_public' => true,
        ]);

        $response = $this->getJson("/api/collections/{$collection->id}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $collection->id,
                    'title' => $collection->title,
                ],
            ]);
    }

    public function test_private_collection_cannot_be_viewed_by_others()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $collection = Collection::factory()->create([
            'user_id' => $user->id,
            'is_public' => false,
        ]);

        Sanctum::actingAs($otherUser);

        $response = $this->getJson("/api/collections/{$collection->id}");

        $response->assertStatus(403);
    }

    public function test_collection_owner_can_view_their_private_collection()
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->create([
            'user_id' => $user->id,
            'is_public' => false,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/collections/{$collection->id}");

        $response->assertStatus(200);
    }

    public function test_collections_can_be_filtered_by_public_status()
    {
        $user = User::factory()->create();
        Collection::factory()->create(['user_id' => $user->id, 'is_public' => true]);
        Collection::factory()->create(['user_id' => $user->id, 'is_public' => false]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/collections?public=true');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_collections_can_be_searched_by_title()
    {
        $user = User::factory()->create();
        Collection::factory()->create(['user_id' => $user->id, 'title' => 'Programming Videos']);
        Collection::factory()->create(['user_id' => $user->id, 'title' => 'Cooking Videos']);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/collections?search=Programming');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_collections_support_pagination()
    {
        $user = User::factory()->create();
        Collection::factory(15)->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/collections?per_page=5');

        $response->assertStatus(200);
        $this->assertCount(5, $response->json('data'));
        $this->assertEquals(15, $response->json('meta.total'));
    }

    public function test_user_collections_endpoint()
    {
        $user = User::factory()->create();
        Collection::factory(3)->create(['user_id' => $user->id, 'is_public' => true]);
        Collection::factory(2)->create(['user_id' => $user->id, 'is_public' => false]);

        $response = $this->getJson("/api/users/{$user->id}/collections");

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data')); // Only public collections
    }

    public function test_collection_with_videos_and_tags()
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->create(['user_id' => $user->id]);
        $video = Video::factory()->create();
        $tag = Tag::factory()->create();

        $collection->videos()->attach($video->id, ['position' => 1]);
        $collection->tags()->attach($tag->id);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/collections/{$collection->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'title',
                    'videos' => [
                        '*' => [
                            'id',
                            'title',
                            'pivot' => [
                                'position',
                                'curator_notes',
                            ],
                        ],
                    ],
                    'tags' => [
                        '*' => [
                            'id',
                            'name',
                        ],
                    ],
                ],
            ]);
    }

    public function test_collection_creation_with_tags()
    {
        $user = User::factory()->create();
        $tag = Tag::factory()->create();

        Sanctum::actingAs($user);

        $collectionData = [
            'title' => 'Tagged Collection',
            'layout' => 'grid',
            'tags' => [$tag->id],
        ];

        $response = $this->postJson('/api/collections', $collectionData);

        $response->assertStatus(201);

        $collection = Collection::find($response->json('collection.id'));
        $this->assertCount(1, $collection->tags);
        $this->assertEquals($tag->id, $collection->tags->first()->id);
    }
}
