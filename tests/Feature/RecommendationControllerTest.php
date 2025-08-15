<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Collection;
use App\Models\Like;
use App\Models\Tag;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RecommendationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_personalized_recommendations_requires_authentication()
    {
        $response = $this->getJson('/api/recommendations');

        $response->assertStatus(401);
    }

    public function test_personalized_recommendations_returns_collections_and_videos()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/recommendations');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'collections',
                'videos',
                'meta' => [
                    'user_interests',
                    'total_collections',
                    'total_videos',
                ],
            ]);
    }

    public function test_personalized_recommendations_based_on_user_interests()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

                $tag = Tag::factory()->create(['name' => 'Technology']);

        // Create a collection with the tag that the user likes
        $likedCollection = Collection::factory()->withDefaults()->create([
            'user_id' => User::factory()->create()->id, // Different user
        ]);
        $likedCollection->tags()->attach($tag->id);

        // Create a like for the user
        Like::factory()->create([
            'user_id' => $user->id,
            'likeable_type' => Collection::class,
            'likeable_id' => $likedCollection->id,
        ]);

        // Create another collection with the same tag from a different user
        $recommendedCollection = Collection::factory()->withDefaults()->create([
            'user_id' => User::factory()->create()->id, // Different user
        ]);
        $recommendedCollection->tags()->attach($tag->id);

        $response = $this->getJson('/api/recommendations');

        $response->assertStatus(200);

        $this->assertCount(2, $response->json('collections'));
        $collectionIds = collect($response->json('collections'))->pluck('id')->toArray();
        $this->assertContains($recommendedCollection->id, $collectionIds);
    }

    public function test_similar_collections_returns_collections_with_similar_tags()
    {
        $tag1 = Tag::factory()->create(['name' => 'Technology']);
        $tag2 = Tag::factory()->create(['name' => 'Programming']);

        $collection1 = Collection::factory()->withDefaults()->create();
        $collection1->tags()->attach([$tag1->id, $tag2->id]);

        $collection2 = Collection::factory()->withDefaults()->create();
        $collection2->tags()->attach($tag1->id);

        $collection3 = Collection::factory()->withDefaults()->create();
        $collection3->tags()->attach($tag2->id);

        $response = $this->getJson("/api/collections/{$collection1->id}/similar");

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_similar_collections_returns_collections_from_same_user()
    {
        $user = User::factory()->create();

        $collection1 = Collection::factory()->withDefaults()->create([
            'user_id' => $user->id,
        ]);
        $collection2 = Collection::factory()->withDefaults()->create([
            'user_id' => $user->id,
        ]);
        $collection3 = Collection::factory()->withDefaults()->create([
            'user_id' => User::factory()->create()->id,
        ]);

        $response = $this->getJson("/api/collections/{$collection1->id}/similar");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($collection2->id, $response->json('data.0.id'));
    }

    public function test_similar_videos_returns_videos_from_same_channel()
    {
        $video1 = Video::factory()->withDefaults()->create([
            'channel_id' => 'channel123',
        ]);
        $video2 = Video::factory()->withDefaults()->create([
            'channel_id' => 'channel123',
        ]);
        $video3 = Video::factory()->withDefaults()->create([
            'channel_id' => 'channel456',
        ]);

        $response = $this->getJson("/api/videos/{$video1->id}/similar");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($video2->id, $response->json('data.0.id'));
    }

    public function test_similar_videos_returns_videos_with_same_category()
    {
        $video1 = Video::factory()->withDefaults()->create([
            'metadata' => ['category' => 'Technology'],
        ]);
        $video2 = Video::factory()->withDefaults()->create([
            'metadata' => ['category' => 'Technology'],
        ]);
        $video3 = Video::factory()->withDefaults()->create([
            'metadata' => ['category' => 'Cooking'],
        ]);

        $response = $this->getJson("/api/videos/{$video1->id}/similar");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($video2->id, $response->json('data.0.id'));
    }

    public function test_suggested_users_requires_authentication()
    {
        $response = $this->getJson('/api/recommendations/users');

        $response->assertStatus(401);
    }

    public function test_suggested_users_returns_users_not_already_followed()
    {
        $user = User::factory()->create();
        $userToFollow = User::factory()->create();
        Sanctum::actingAs($user);

        // Create collections for the user to follow
        Collection::factory()->withDefaults()->create([
            'user_id' => $userToFollow->id,
        ]);

        $response = $this->getJson('/api/recommendations/users');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($userToFollow->id, $response->json('data.0.id'));
    }

    public function test_suggested_users_excludes_already_followed_users()
    {
        $user = User::factory()->create();
        $userToFollow = User::factory()->create();
        Sanctum::actingAs($user);

        // Create a follow relationship
        $user->follows()->create([
            'following_id' => $userToFollow->id,
        ]);

        // Create collections for the user to follow
        Collection::factory()->withDefaults()->create([
            'user_id' => $userToFollow->id,
        ]);

        $response = $this->getJson('/api/recommendations/users');

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('data'));
    }

    public function test_suggested_users_only_includes_users_with_public_collections()
    {
        $user = User::factory()->create();
        $userWithPublicCollections = User::factory()->create();
        $userWithPrivateCollections = User::factory()->create();
        Sanctum::actingAs($user);

        Collection::factory()->withDefaults()->create([
            'user_id' => $userWithPublicCollections->id,
            'is_public' => true,
        ]);
        Collection::factory()->withDefaults()->create([
            'user_id' => $userWithPrivateCollections->id,
            'is_public' => false,
        ]);

        $response = $this->getJson('/api/recommendations/users');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($userWithPublicCollections->id, $response->json('data.0.id'));
    }

    public function test_based_on_history_requires_authentication()
    {
        $response = $this->getJson('/api/recommendations/history');

        $response->assertStatus(401);
    }

    public function test_based_on_history_returns_collections_from_viewed_users()
    {
        $user = User::factory()->create();
        $viewedUser = User::factory()->create();
        Sanctum::actingAs($user);

        // Create a collection that the user has viewed
        $viewedCollection = Collection::factory()->withDefaults()->create([
            'user_id' => $viewedUser->id,
            'is_public' => true,
        ]);

        // Create activity log for viewing the collection
        ActivityLog::create([
            'user_id' => $user->id,
            'subject_type' => Collection::class,
            'subject_id' => $viewedCollection->id,
            'action' => 'viewed',
        ]);

        // Create another collection from the same user (the one who created the viewed collection)
        $recommendedCollection = Collection::factory()->withDefaults()->create([
            'user_id' => $viewedUser->id,
            'is_public' => true,
        ]);

        $response = $this->getJson('/api/recommendations/history');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($recommendedCollection->id, $response->json('data.0.id'));
    }

    public function test_recommendation_endpoints_support_pagination()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        Collection::factory(20)->withDefaults()->create();

        $response = $this->getJson('/api/recommendations?per_page=5');

        $response->assertStatus(200);
        $this->assertCount(5, $response->json('collections'));
    }

    public function test_recommendation_endpoints_validate_per_page_limits()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/recommendations?per_page=100');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['per_page']);
    }

    public function test_similar_collections_excludes_private_collections()
    {
        $tag = Tag::factory()->create(['name' => 'Technology']);

        $publicCollection = Collection::factory()->withDefaults()->create([
            'is_public' => true,
        ]);
        $publicCollection->tags()->attach($tag->id);

        $privateCollection = Collection::factory()->withDefaults()->create([
            'is_public' => false,
        ]);
        $privateCollection->tags()->attach($tag->id);

        $response = $this->getJson("/api/collections/{$publicCollection->id}/similar");

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('data'));
    }

    public function test_similar_collections_excludes_self()
    {
        $collection = Collection::factory()->withDefaults()->create();

        $response = $this->getJson("/api/collections/{$collection->id}/similar");

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('data'));
    }

    public function test_similar_videos_excludes_self()
    {
        $video = Video::factory()->withDefaults()->create();

        $response = $this->getJson("/api/videos/{$video->id}/similar");

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('data'));
    }

    public function test_personalized_recommendations_excludes_user_own_content()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Create user's own collection
        Collection::factory()->withDefaults()->create([
            'user_id' => $user->id,
        ]);

        $response = $this->getJson('/api/recommendations');

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('collections'));
    }

    public function test_recommendation_endpoints_handle_empty_results()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/recommendations');

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('collections'));
        $this->assertCount(0, $response->json('videos'));
    }

    public function test_user_interests_are_calculated_correctly()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $tag = Tag::factory()->create(['name' => 'Technology']);

        // Create user's own collection with the tag
        $ownCollection = Collection::factory()->withDefaults()->create([
            'user_id' => $user->id,
        ]);
        $ownCollection->tags()->attach($tag->id);

        $response = $this->getJson('/api/recommendations');

        $response->assertStatus(200);
        $this->assertArrayHasKey('tags', $response->json('meta.user_interests'));
        $this->assertEquals(2, $response->json('meta.user_interests.tags.Technology'));
    }
}
