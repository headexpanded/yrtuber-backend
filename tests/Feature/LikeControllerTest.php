<?php

namespace Tests\Feature;

use App\Models\Collection;
use App\Models\Like;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LikeControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_like_collection()
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->withDefaults()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/likes', [
            'likeable_type' => 'App\Models\Collection',
            'likeable_id' => $collection->id,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Liked successfully',
            ]);

        $this->assertDatabaseHas('likes', [
            'user_id' => $user->id,
            'likeable_type' => 'App\Models\Collection',
            'likeable_id' => $collection->id,
        ]);

        $this->assertDatabaseHas('collections', [
            'id' => $collection->id,
            'like_count' => 1,
        ]);
    }

    public function test_authenticated_user_can_like_video()
    {
        $user = User::factory()->create();
        $video = Video::factory()->withDefaults()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/likes', [
            'likeable_type' => 'App\Models\Video',
            'likeable_id' => $video->id,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Liked successfully',
            ]);

        $this->assertDatabaseHas('likes', [
            'user_id' => $user->id,
            'likeable_type' => 'App\Models\Video',
            'likeable_id' => $video->id,
        ]);

        $this->assertDatabaseHas('videos', [
            'id' => $video->id,
            'like_count' => 1,
        ]);
    }

    public function test_user_cannot_like_same_resource_twice()
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->withDefaults()->create();

        Sanctum::actingAs($user);

        // First like
        $this->postJson('/api/likes', [
            'likeable_type' => 'App\Models\Collection',
            'likeable_id' => $collection->id,
        ]);

        // Second like attempt
        $response = $this->postJson('/api/likes', [
            'likeable_type' => 'App\Models\Collection',
            'likeable_id' => $collection->id,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Already liked',
            ]);
    }

    public function test_like_validation_requires_likeable_type()
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->withDefaults()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/likes', [
            'likeable_id' => $collection->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['likeable_type']);
    }

    public function test_like_validation_requires_likeable_id()
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/likes', [
            'likeable_type' => 'App\Models\Collection',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['likeable_id']);
    }

    public function test_like_validation_requires_valid_likeable_type()
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->withDefaults()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/likes', [
            'likeable_type' => 'App\Models\InvalidModel',
            'likeable_id' => $collection->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['likeable_type']);
    }

    public function test_like_returns_404_for_nonexistent_resource()
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/likes', [
            'likeable_type' => 'App\Models\Collection',
            'likeable_id' => 999,
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'message' => 'Resource not found',
            ]);
    }

    public function test_authenticated_user_can_unlike_collection()
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->withDefaults()->create(['like_count' => 1]);
        Like::factory()->create([
            'user_id' => $user->id,
            'likeable_type' => 'App\Models\Collection',
            'likeable_id' => $collection->id,
        ]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/likes', [
            'likeable_type' => 'App\Models\Collection',
            'likeable_id' => $collection->id,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Unliked successfully',
            ]);

        $this->assertDatabaseMissing('likes', [
            'user_id' => $user->id,
            'likeable_type' => 'App\Models\Collection',
            'likeable_id' => $collection->id,
        ]);

        $this->assertDatabaseHas('collections', [
            'id' => $collection->id,
            'like_count' => 0,
        ]);
    }

    public function test_authenticated_user_can_unlike_video()
    {
        $user = User::factory()->create();
        $video = Video::factory()->withDefaults()->create(['like_count' => 1]);
        Like::factory()->create([
            'user_id' => $user->id,
            'likeable_type' => 'App\Models\Video',
            'likeable_id' => $video->id,
        ]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/likes', [
            'likeable_type' => 'App\Models\Video',
            'likeable_id' => $video->id,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Unliked successfully',
            ]);

        $this->assertDatabaseMissing('likes', [
            'user_id' => $user->id,
            'likeable_type' => 'App\Models\Video',
            'likeable_id' => $video->id,
        ]);

        $this->assertDatabaseHas('videos', [
            'id' => $video->id,
            'like_count' => 0,
        ]);
    }

    public function test_unlike_returns_404_for_nonexistent_like()
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->withDefaults()->create();

        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/likes', [
            'likeable_type' => 'App\Models\Collection',
            'likeable_id' => $collection->id,
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'message' => 'Like not found',
            ]);
    }

    public function test_public_can_view_likes_for_collection()
    {
        $collection = Collection::factory()->withDefaults()->create();
        $users = User::factory(3)->create();

        foreach ($users as $user) {
            Like::factory()->create([
                'user_id' => $user->id,
                'likeable_type' => 'App\Models\Collection',
                'likeable_id' => $collection->id,
            ]);
        }

        $response = $this->getJson('/api/likes?likeable_type=App\Models\Collection&likeable_id=' . $collection->id);

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data'));
    }

    public function test_public_can_view_likes_for_video()
    {
        $video = Video::factory()->withDefaults()->create();
        $users = User::factory(3)->create();

        foreach ($users as $user) {
            Like::factory()->create([
                'user_id' => $user->id,
                'likeable_type' => 'App\Models\Video',
                'likeable_id' => $video->id,
            ]);
        }

        $response = $this->getJson('/api/likes?likeable_type=App\Models\Video&likeable_id=' . $video->id);

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data'));
    }

    public function test_likes_support_pagination()
    {
        $collection = Collection::factory()->withDefaults()->create();
        $users = User::factory(15)->create();

        foreach ($users as $user) {
            Like::factory()->create([
                'user_id' => $user->id,
                'likeable_type' => 'App\Models\Collection',
                'likeable_id' => $collection->id,
            ]);
        }

        $response = $this->getJson('/api/likes?likeable_type=App\Models\Collection&likeable_id=' . $collection->id . '&per_page=5');

        $response->assertStatus(200);
        $this->assertCount(5, $response->json('data'));
        $this->assertEquals(15, $response->json('meta.total'));
    }

    public function test_authenticated_user_can_check_if_liked()
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->withDefaults()->create();
        Like::factory()->create([
            'user_id' => $user->id,
            'likeable_type' => 'App\Models\Collection',
            'likeable_id' => $collection->id,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/likes/check?likeable_type=App\Models\Collection&likeable_id=' . $collection->id);

        $response->assertStatus(200)
            ->assertJson([
                'is_liked' => true,
            ]);
    }

    public function test_authenticated_user_can_check_if_not_liked()
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->withDefaults()->create();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/likes/check?likeable_type=App\Models\Collection&likeable_id=' . $collection->id);

        $response->assertStatus(200)
            ->assertJson([
                'is_liked' => false,
            ]);
    }

    public function test_likes_include_user_profile_information()
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->withDefaults()->create();
        Like::factory()->create([
            'user_id' => $user->id,
            'likeable_type' => 'App\Models\Collection',
            'likeable_id' => $collection->id,
        ]);

        $response = $this->getJson('/api/likes?likeable_type=App\Models\Collection&likeable_id=' . $collection->id);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'user' => [
                            'id',
                            'username',
                            'profile',
                        ],
                        'created_at',
                    ],
                ],
            ]);
    }
}
