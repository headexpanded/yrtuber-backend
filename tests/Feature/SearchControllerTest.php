<?php

namespace Tests\Feature;

use App\Models\Collection;
use App\Models\Tag;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SearchControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_search_returns_collections_videos_and_users()
    {
        // Create test data
        $user = User::factory()->create();
        $collection = Collection::factory()->withDefaults()->create([
            'title' => 'Amazing Tech Collection',
            'description' => 'Best tech videos ever',
        ]);
        $video = Video::factory()->withDefaults()->create([
            'title' => 'Amazing Tech Video',
            'description' => 'Learn amazing tech',
        ]);

        $response = $this->getJson('/api/search?query=amazing');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'query',
                'type',
                'results' => [
                    'collections',
                    'videos',
                    'users',
                ],
                'meta' => [
                    'total_collections',
                    'total_videos',
                    'total_users',
                ],
            ]);
    }

    public function test_global_search_validates_query_parameter()
    {
        $response = $this->getJson('/api/search');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['query']);
    }

    public function test_global_search_validates_query_minimum_length()
    {
        $response = $this->getJson('/api/search?query=a');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['query']);
    }

    public function test_global_search_validates_type_parameter()
    {
        $response = $this->getJson('/api/search?query=test&type=invalid');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    public function test_global_search_can_filter_by_type()
    {
        $collection = Collection::factory()->withDefaults()->create([
            'title' => 'Test Collection',
        ]);
        $video = Video::factory()->withDefaults()->create([
            'title' => 'Test Video',
        ]);

        $response = $this->getJson('/api/search?query=test&type=collections');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('results.collections'));
        $this->assertArrayNotHasKey('videos', $response->json('results'));
    }

    public function test_collections_search_returns_matching_collections()
    {
        $collection1 = Collection::factory()->withDefaults()->create([
            'title' => 'Tech Collection',
            'description' => 'Amazing tech content',
        ]);
        $collection2 = Collection::factory()->withDefaults()->create([
            'title' => 'Cooking Collection',
            'description' => 'Delicious recipes',
        ]);

        $response = $this->getJson('/api/search/collections?query=tech');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Tech Collection', $response->json('data.0.title'));
    }

    public function test_collections_search_supports_sorting()
    {
        $collection1 = Collection::factory()->withDefaults()->create([
            'title' => 'Popular Collection',
            'view_count' => 100,
            'like_count' => 50,
        ]);
        $collection2 = Collection::factory()->withDefaults()->create([
            'title' => 'New Collection',
            'view_count' => 10,
            'like_count' => 5,
        ]);

        $response = $this->getJson('/api/search/collections?query=collection&sort=popular');

        $response->assertStatus(200);
        $this->assertEquals('Popular Collection', $response->json('data.0.title'));
    }

    public function test_videos_search_returns_matching_videos()
    {
        $video1 = Video::factory()->withDefaults()->create([
            'title' => 'Tech Tutorial',
            'description' => 'Learn programming',
        ]);
        $video2 = Video::factory()->withDefaults()->create([
            'title' => 'Cooking Tutorial',
            'description' => 'Learn cooking',
        ]);

        $response = $this->getJson('/api/search/videos?query=tech');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Tech Tutorial', $response->json('data.0.title'));
    }

    public function test_videos_search_supports_duration_filter()
    {
        $video1 = Video::factory()->withDefaults()->create([
            'title' => 'Short Video',
            'duration' => 180, // 3 minutes
        ]);
        $video2 = Video::factory()->withDefaults()->create([
            'title' => 'Long Video',
            'duration' => 1800, // 30 minutes
        ]);

        $response = $this->getJson('/api/search/videos?query=video&duration=short');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Short Video', $response->json('data.0.title'));
    }

    public function test_users_search_returns_matching_users()
    {
        $user1 = User::factory()->create(['username' => 'techguru']);
        $user2 = User::factory()->create(['username' => 'cookmaster']);

        $response = $this->getJson('/api/search/users?query=tech');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('techguru', $response->json('data.0.username'));
    }

    public function test_users_search_supports_sorting()
    {
        $user1 = User::factory()->create(['username' => 'user1']);
        $user2 = User::factory()->create(['username' => 'user2']);

        $response = $this->getJson('/api/search/users?query=user&sort=recent');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_search_supports_pagination()
    {
        // Create more than 15 items to test pagination
        Collection::factory(20)->withDefaults()->create([
            'title' => 'Test Collection',
        ]);

        $response = $this->getJson('/api/search/collections?query=test&per_page=5');

        $response->assertStatus(200);
        $this->assertCount(5, $response->json('data'));
        $this->assertEquals(20, $response->json('meta.total'));
        $this->assertEquals(5, $response->json('meta.per_page'));
    }

    public function test_search_validates_per_page_limits()
    {
        $response = $this->getJson('/api/search/collections?query=test&per_page=100');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['per_page']);
    }

    public function test_search_only_returns_public_collections()
    {
        $publicCollection = Collection::factory()->withDefaults()->create([
            'title' => 'Public Collection',
            'is_public' => true,
        ]);
        $privateCollection = Collection::factory()->withDefaults()->create([
            'title' => 'Private Collection',
            'is_public' => false,
        ]);

        $response = $this->getJson('/api/search/collections?query=collection');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Public Collection', $response->json('data.0.title'));
    }

    public function test_search_includes_relationships()
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->withDefaults()->create([
            'user_id' => $user->id,
            'title' => 'Test Collection',
        ]);

        $response = $this->getJson('/api/search/collections?query=test');

        $response->assertStatus(200);
        $this->assertArrayHasKey('user', $response->json('data.0'));
    }

    public function test_search_handles_empty_results()
    {
        $response = $this->getJson('/api/search?query=nonexistent');

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('results.collections'));
        $this->assertCount(0, $response->json('results.videos'));
        $this->assertCount(0, $response->json('results.users'));
    }

    public function test_search_is_case_insensitive()
    {
        $collection = Collection::factory()->withDefaults()->create([
            'title' => 'UPPERCASE COLLECTION',
        ]);

        $response = $this->getJson('/api/search/collections?query=uppercase');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_search_matches_partial_words()
    {
        $collection = Collection::factory()->withDefaults()->create([
            'title' => 'Programming Tutorial Collection',
        ]);

        $response = $this->getJson('/api/search/collections?query=program');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }
}
