<?php

namespace Tests\Feature;

use App\Models\Follow;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FollowControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_follow_another_user()
    {
        $follower = User::factory()->create();
        $following = User::factory()->create();
        UserProfile::factory()->withDefaults()->create(['user_id' => $follower->id]);
        UserProfile::factory()->withDefaults()->create(['user_id' => $following->id]);

        Sanctum::actingAs($follower);

        $response = $this->postJson('/api/follows', [
            'following_id' => $following->id,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Followed successfully',
            ]);

        $this->assertDatabaseHas('follows', [
            'follower_id' => $follower->id,
            'following_id' => $following->id,
        ]);

        // Check that follower counts are updated
        $this->assertDatabaseHas('user_profiles', [
            'user_id' => $following->id,
            'follower_count' => 1,
        ]);

        $this->assertDatabaseHas('user_profiles', [
            'user_id' => $follower->id,
            'following_count' => 1,
        ]);
    }

    public function test_user_cannot_follow_themselves()
    {
        $user = User::factory()->create();
        UserProfile::factory()->withDefaults()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/follows', [
            'following_id' => $user->id,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Cannot follow yourself',
            ]);
    }

    public function test_user_cannot_follow_same_user_twice()
    {
        $follower = User::factory()->create();
        $following = User::factory()->create();
        UserProfile::factory()->withDefaults()->create(['user_id' => $follower->id]);
        UserProfile::factory()->withDefaults()->create(['user_id' => $following->id]);

        Sanctum::actingAs($follower);

        // First follow
        $this->postJson('/api/follows', [
            'following_id' => $following->id,
        ]);

        // Second follow attempt
        $response = $this->postJson('/api/follows', [
            'following_id' => $following->id,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Already following this user',
            ]);
    }

    public function test_follow_validation_requires_following_id()
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/follows', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['following_id']);
    }

    public function test_follow_validation_requires_valid_user_id()
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/follows', [
            'following_id' => 999,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['following_id']);
    }

    public function test_authenticated_user_can_unfollow_user()
    {
        $follower = User::factory()->create();
        $following = User::factory()->create();
        UserProfile::factory()->withDefaults()->create(['user_id' => $follower->id, 'following_count' => 1]);
        UserProfile::factory()->withDefaults()->create(['user_id' => $following->id, 'follower_count' => 1]);

        Follow::factory()->create([
            'follower_id' => $follower->id,
            'following_id' => $following->id,
        ]);

        Sanctum::actingAs($follower);

        $response = $this->deleteJson('/api/follows', [
            'following_id' => $following->id,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Unfollowed successfully',
            ]);

        $this->assertDatabaseMissing('follows', [
            'follower_id' => $follower->id,
            'following_id' => $following->id,
        ]);

        // Check that follower counts are updated
        $this->assertDatabaseHas('user_profiles', [
            'user_id' => $following->id,
            'follower_count' => 0,
        ]);

        $this->assertDatabaseHas('user_profiles', [
            'user_id' => $follower->id,
            'following_count' => 0,
        ]);
    }

    public function test_unfollow_returns_404_for_nonexistent_follow()
    {
        $follower = User::factory()->create();
        $following = User::factory()->create();

        Sanctum::actingAs($follower);

        $response = $this->deleteJson('/api/follows', [
            'following_id' => $following->id,
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'message' => 'Follow relationship not found',
            ]);
    }

        public function test_authenticated_user_can_view_their_following_list()
    {
        $user = User::factory()->create();
        UserProfile::factory()->withDefaults()->create(['user_id' => $user->id]);
        $followingUsers = User::factory(3)->create();

        foreach ($followingUsers as $followingUser) {
            UserProfile::factory()->withDefaults()->create(['user_id' => $followingUser->id]);
            Follow::factory()->create([
                'follower_id' => $user->id,
                'following_id' => $followingUser->id,
            ]);
        }

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/follows/following');

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data'));
    }

        public function test_authenticated_user_can_view_their_followers_list()
    {
        $user = User::factory()->create();
        UserProfile::factory()->withDefaults()->create(['user_id' => $user->id]);
        $followerUsers = User::factory(3)->create();

        foreach ($followerUsers as $followerUser) {
            UserProfile::factory()->withDefaults()->create(['user_id' => $followerUser->id]);
            Follow::factory()->create([
                'follower_id' => $followerUser->id,
                'following_id' => $user->id,
            ]);
        }

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/follows/followers');

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data'));
    }

        public function test_following_list_supports_pagination()
    {
        $user = User::factory()->create();
        UserProfile::factory()->withDefaults()->create(['user_id' => $user->id]);
        $followingUsers = User::factory(15)->create();

        foreach ($followingUsers as $followingUser) {
            UserProfile::factory()->withDefaults()->create(['user_id' => $followingUser->id]);
            Follow::factory()->create([
                'follower_id' => $user->id,
                'following_id' => $followingUser->id,
            ]);
        }

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/follows/following?per_page=5');

        $response->assertStatus(200);
        $this->assertCount(5, $response->json('data'));
        $this->assertEquals(15, $response->json('meta.total'));
    }

        public function test_followers_list_supports_pagination()
    {
        $user = User::factory()->create();
        UserProfile::factory()->withDefaults()->create(['user_id' => $user->id]);
        $followerUsers = User::factory(15)->create();

        foreach ($followerUsers as $followerUser) {
            UserProfile::factory()->withDefaults()->create(['user_id' => $followerUser->id]);
            Follow::factory()->create([
                'follower_id' => $followerUser->id,
                'following_id' => $user->id,
            ]);
        }

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/follows/followers?per_page=5');

        $response->assertStatus(200);
        $this->assertCount(5, $response->json('data'));
        $this->assertEquals(15, $response->json('meta.total'));
    }

        public function test_public_can_view_user_followers()
    {
        $user = User::factory()->create();
        UserProfile::factory()->withDefaults()->create(['user_id' => $user->id]);
        $followerUsers = User::factory(3)->create();

        foreach ($followerUsers as $followerUser) {
            UserProfile::factory()->withDefaults()->create(['user_id' => $followerUser->id]);
            Follow::factory()->create([
                'follower_id' => $followerUser->id,
                'following_id' => $user->id,
            ]);
        }

        $response = $this->getJson('/api/users/' . $user->id . '/followers');

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data'));
    }

        public function test_public_can_view_user_following()
    {
        $user = User::factory()->create();
        UserProfile::factory()->withDefaults()->create(['user_id' => $user->id]);
        $followingUsers = User::factory(3)->create();

        foreach ($followingUsers as $followingUser) {
            UserProfile::factory()->withDefaults()->create(['user_id' => $followingUser->id]);
            Follow::factory()->create([
                'follower_id' => $user->id,
                'following_id' => $followingUser->id,
            ]);
        }

        $response = $this->getJson('/api/users/' . $user->id . '/following');

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data'));
    }

    public function test_authenticated_user_can_check_if_following()
    {
        $follower = User::factory()->create();
        $following = User::factory()->create();
        UserProfile::factory()->withDefaults()->create(['user_id' => $follower->id]);
        UserProfile::factory()->withDefaults()->create(['user_id' => $following->id]);

        Follow::factory()->create([
            'follower_id' => $follower->id,
            'following_id' => $following->id,
        ]);

        Sanctum::actingAs($follower);

        $response = $this->getJson('/api/follows/check?following_id=' . $following->id);

        $response->assertStatus(200)
            ->assertJson([
                'is_following' => true,
            ]);
    }

    public function test_authenticated_user_can_check_if_not_following()
    {
        $follower = User::factory()->create();
        $following = User::factory()->create();
        UserProfile::factory()->withDefaults()->create(['user_id' => $follower->id]);
        UserProfile::factory()->withDefaults()->create(['user_id' => $following->id]);

        Sanctum::actingAs($follower);

        $response = $this->getJson('/api/follows/check?following_id=' . $following->id);

        $response->assertStatus(200)
            ->assertJson([
                'is_following' => false,
            ]);
    }

    public function test_follow_check_validation_requires_following_id()
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/follows/check');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['following_id']);
    }

    public function test_follow_check_validation_requires_valid_user_id()
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/follows/check?following_id=999');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['following_id']);
    }

    public function test_follow_lists_include_user_profile_information()
    {
        $user = User::factory()->create();
        UserProfile::factory()->withDefaults()->create(['user_id' => $user->id]);
        $followingUser = User::factory()->create();
        UserProfile::factory()->withDefaults()->create(['user_id' => $followingUser->id]);

        Follow::factory()->create([
            'follower_id' => $user->id,
            'following_id' => $followingUser->id,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/follows/following');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'username',
                        'profile' => [
                            'username',
                            'avatar',
                            'bio',
                            'is_verified',
                            'follower_count',
                            'following_count',
                        ],
                        'followed_at',
                    ],
                ],
            ]);
    }

        public function test_follow_lists_are_ordered_by_created_at_desc()
    {
        $user = User::factory()->create();
        UserProfile::factory()->withDefaults()->create(['user_id' => $user->id]);
        $followingUsers = User::factory(3)->create();

        foreach ($followingUsers as $index => $followingUser) {
            UserProfile::factory()->withDefaults()->create(['user_id' => $followingUser->id]);
            Follow::factory()->create([
                'follower_id' => $user->id,
                'following_id' => $followingUser->id,
                'created_at' => now()->subDays(2 - $index), // Different creation times
            ]);
        }

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/follows/following');

        $response->assertStatus(200);
        $data = $response->json('data');

        // Should be ordered by created_at desc (newest first)
        $this->assertEquals($followingUsers[2]->id, $data[0]['id']);
        $this->assertEquals($followingUsers[1]->id, $data[1]['id']);
        $this->assertEquals($followingUsers[0]->id, $data[2]['id']);
    }

    public function test_follow_counts_are_updated_correctly()
    {
        $follower = User::factory()->create();
        $following = User::factory()->create();
        UserProfile::factory()->withDefaults()->create(['user_id' => $follower->id, 'following_count' => 0, 'follower_count' => 0]);
        UserProfile::factory()->withDefaults()->create(['user_id' => $following->id, 'following_count' => 0, 'follower_count' => 0]);

        Sanctum::actingAs($follower);

        // Follow
        $this->postJson('/api/follows', [
            'following_id' => $following->id,
        ]);

        $this->assertDatabaseHas('user_profiles', [
            'user_id' => $follower->id,
            'following_count' => 1,
        ]);

        $this->assertDatabaseHas('user_profiles', [
            'user_id' => $following->id,
            'follower_count' => 1,
        ]);

        // Unfollow
        $this->deleteJson('/api/follows', [
            'following_id' => $following->id,
        ]);

        $this->assertDatabaseHas('user_profiles', [
            'user_id' => $follower->id,
            'following_count' => 0,
        ]);

        $this->assertDatabaseHas('user_profiles', [
            'user_id' => $following->id,
            'follower_count' => 0,
        ]);
    }
}
