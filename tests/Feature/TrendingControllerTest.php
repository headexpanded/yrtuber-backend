<?php

namespace Tests\Feature;

use App\Models\Collection;
use App\Models\Tag;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TrendingControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_trending_collections_returns_popular_collections()
    {
        $collection1 = Collection::factory()->withDefaults()->create([
            'view_count' => 100,
            'like_count' => 50,
        ]);
        $collection2 = Collection::factory()->withDefaults()->create([
            'view_count' => 10,
            'like_count' => 5,
        ]);

        $response = $this->getJson('/api/trending/collections');

        $response->assertStatus(200);
        $this->assertEquals($collection1->id, $response->json('data.0.id'));
    }

    public function test_trending_collections_supports_period_filter()
    {
        $oldCollection = Collection::factory()->withDefaults()->create([
            'created_at' => now()->subMonth(),
            'view_count' => 1000,
        ]);
        $newCollection = Collection::factory()->withDefaults()->create([
            'created_at' => now(),
            'view_count' => 100,
        ]);

        $response = $this->getJson('/api/trending/collections?period=week');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($newCollection->id, $response->json('data.0.id'));
    }

    public function test_trending_collections_supports_category_filter()
    {
        $tag = Tag::factory()->create(['name' => 'Technology']);
        $collection1 = Collection::factory()->withDefaults()->create();
        $collection1->tags()->attach($tag->id);

        $collection2 = Collection::factory()->withDefaults()->create();

        $response = $this->getJson('/api/trending/collections?category=Technology');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($collection1->id, $response->json('data.0.id'));
    }

    public function test_trending_collections_validates_period_parameter()
    {
        $response = $this->getJson('/api/trending/collections?period=invalid');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['period']);
    }

    public function test_trending_videos_returns_popular_videos()
    {
        $video1 = Video::factory()->withDefaults()->create([
            'view_count' => 1000,
            'like_count' => 500,
        ]);
        $video2 = Video::factory()->withDefaults()->create([
            'view_count' => 100,
            'like_count' => 50,
        ]);

        $response = $this->getJson('/api/trending/videos');

        $response->assertStatus(200);
        $this->assertEquals($video1->id, $response->json('data.0.id'));
    }

    public function test_trending_videos_supports_duration_filter()
    {
        // Create videos with explicit view/like counts to ensure proper ordering
        $shortVideo = Video::factory()->withDefaults()->create([
            'duration' => 180, // 3 minutes
            'view_count' => 1000,
            'like_count' => 500,
        ]);
        $longVideo = Video::factory()->withDefaults()->create([
            'duration' => 1800, // 30 minutes
            'view_count' => 100,
            'like_count' => 50,
        ]);

        $response = $this->getJson('/api/trending/videos?duration=short');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($shortVideo->id, $response->json('data.0.id'));
    }

    public function test_trending_videos_supports_category_filter()
    {
        // Create videos with explicit view/like counts to ensure proper ordering
        $video1 = Video::factory()->withDefaults()->create([
            'metadata' => ['category' => 'Technology'],
            'view_count' => 1000,
            'like_count' => 500,
        ]);
        $video2 = Video::factory()->withDefaults()->create([
            'metadata' => ['category' => 'Cooking'],
            'view_count' => 100,
            'like_count' => 50,
        ]);

        $response = $this->getJson('/api/trending/videos?category=Technology');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($video1->id, $response->json('data.0.id'));
    }

    public function test_trending_creators_returns_users_with_popular_content()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // Create collections with explicit view/like counts to ensure proper ordering
        Collection::factory(3)->withDefaults()->create([
            'user_id' => $user1->id,
            'view_count' => 100,
            'like_count' => 50,
        ]);
        Collection::factory(1)->withDefaults()->create([
            'user_id' => $user2->id,
            'view_count' => 10,
            'like_count' => 5,
        ]);

        $response = $this->getJson('/api/trending/creators');

        $response->assertStatus(200);
        $this->assertEquals($user1->id, $response->json('data.0.id'));
    }

    public function test_trending_creators_supports_period_filter()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // Create collections with explicit view/like counts to ensure proper ordering
        Collection::factory()->withDefaults()->create([
            'user_id' => $user1->id,
            'created_at' => now()->subMonth(),
            'view_count' => 100,
            'like_count' => 50,
        ]);
        Collection::factory()->withDefaults()->create([
            'user_id' => $user2->id,
            'created_at' => now(),
            'view_count' => 100,
            'like_count' => 50,
        ]);

        $response = $this->getJson('/api/trending/creators?period=week');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($user2->id, $response->json('data.0.id'));
    }

    public function test_trending_categories_returns_popular_tags()
    {
        $tag1 = Tag::factory()->create(['name' => 'Technology']);
        $tag2 = Tag::factory()->create(['name' => 'Cooking']);

        // Create collections with explicit view/like counts to ensure proper ordering
        $collection1 = Collection::factory()->withDefaults()->create([
            'view_count' => 100,
            'like_count' => 50,
        ]);
        $collection1->tags()->attach($tag1->id);

        $collection2 = Collection::factory()->withDefaults()->create([
            'view_count' => 100,
            'like_count' => 50,
        ]);
        $collection2->tags()->attach($tag1->id);

        $collection3 = Collection::factory()->withDefaults()->create([
            'view_count' => 10,
            'like_count' => 5,
        ]);
        $collection3->tags()->attach($tag2->id);

        $response = $this->getJson('/api/trending/categories');

        $response->assertStatus(200);
        $this->assertEquals('Technology', $response->json('data.0.name'));
        $this->assertEquals(2, $response->json('data.0.collections_count'));
    }

    public function test_trending_categories_supports_period_filter()
    {
        $tag = Tag::factory()->create(['name' => 'Technology']);

        // Create collections with explicit view/like counts to ensure proper ordering
        $oldCollection = Collection::factory()->withDefaults()->create([
            'created_at' => now()->subMonth(),
            'view_count' => 100,
            'like_count' => 50,
        ]);
        $oldCollection->tags()->attach($tag->id);

        $newCollection = Collection::factory()->withDefaults()->create([
            'created_at' => now(),
            'view_count' => 100,
            'like_count' => 50,
        ]);
        $newCollection->tags()->attach($tag->id);

        $response = $this->getJson('/api/trending/categories?period=week');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals(1, $response->json('data.0.collections_count'));
    }

    public function test_trending_endpoints_support_pagination()
    {
        Collection::factory(20)->withDefaults()->create();

        $response = $this->getJson('/api/trending/collections?per_page=5');

        $response->assertStatus(200);
        $this->assertCount(5, $response->json('data'));
        $this->assertEquals(20, $response->json('meta.total'));
    }

    public function test_trending_endpoints_validate_per_page_limits()
    {
        $response = $this->getJson('/api/trending/collections?per_page=100');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['per_page']);
    }

    public function test_trending_collections_only_returns_public_collections()
    {
        $publicCollection = Collection::factory()->withDefaults()->create([
            'is_public' => true,
            'view_count' => 100,
        ]);
        $privateCollection = Collection::factory()->withDefaults()->create([
            'is_public' => false,
            'view_count' => 1000,
        ]);

        $response = $this->getJson('/api/trending/collections');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($publicCollection->id, $response->json('data.0.id'));
    }

    public function test_trending_creators_only_includes_users_with_public_collections()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        Collection::factory()->withDefaults()->create([
            'user_id' => $user1->id,
            'is_public' => true,
        ]);
        Collection::factory()->withDefaults()->create([
            'user_id' => $user2->id,
            'is_public' => false,
        ]);

        $response = $this->getJson('/api/trending/creators');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($user1->id, $response->json('data.0.id'));
    }

    public function test_trending_categories_only_includes_tags_from_public_collections()
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

        $response = $this->getJson('/api/trending/categories');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals(1, $response->json('data.0.collections_count'));
    }

    public function test_trending_endpoints_include_relationships()
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->withDefaults()->create([
            'user_id' => $user->id,
        ]);

        $response = $this->getJson('/api/trending/collections');

        $response->assertStatus(200);
        $this->assertArrayHasKey('user', $response->json('data.0'));
    }

    public function test_trending_endpoints_handle_empty_results()
    {
        $response = $this->getJson('/api/trending/collections?period=today');

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('data'));
    }
}
