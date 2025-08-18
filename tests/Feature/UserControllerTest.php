<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_authenticated_user_can_get_their_profile()
    {
        $user = User::factory()->create();
        $profile = UserProfile::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/profile');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'username',
                    'email',
                    'profile' => [
                        'id',
                        'username',
                        'bio',
                        'avatar',
                        'website',
                        'location',
                        'social_links',
                        'is_verified',
                        'is_featured_curator',
                        'follower_count',
                        'following_count',
                        'collection_count',
                    ],
                ],
            ]);
    }

    public function test_unauthenticated_user_cannot_get_profile()
    {
        $response = $this->getJson('/api/profile');

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_update_their_profile()
    {
        $user = User::factory()->create();
        $profile = UserProfile::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $updateData = [
            'username' => 'newusername',
            'bio' => 'Updated bio',
            'website' => 'https://example.com',
            'location' => 'New York',
            'social_links' => [
                'twitter' => 'https://twitter.com/newuser',
                'youtube' => 'https://youtube.com/newuser',
            ],
        ];

        $response = $this->putJson('/api/profile', $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Profile updated successfully',
            ]);

        $this->assertDatabaseHas('user_profiles', [
            'user_id' => $user->id,
            'username' => 'newusername',
            'bio' => 'Updated bio',
        ]);
    }

    public function test_profile_update_validates_required_fields()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->putJson('/api/profile', [
            'username' => '',
            'website' => 'invalid-url',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['username', 'website']);
    }

    public function test_profile_update_validates_unique_username()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        UserProfile::factory()->create(['user_id' => $user2->id, 'username' => 'existinguser']);

        Sanctum::actingAs($user1);

        $response = $this->putJson('/api/profile', [
            'username' => 'existinguser',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['username']);
    }

    public function test_authenticated_user_can_list_users()
    {
        $user = User::factory()->create();
        UserProfile::factory()->create(['user_id' => $user->id]);

        $otherUsers = User::factory(3)->create();
        foreach ($otherUsers as $otherUser) {
            UserProfile::factory()->create(['user_id' => $otherUser->id]);
        }

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/users');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'username',
                        'email',
                        'profile',
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

    public function test_authenticated_user_can_search_users()
    {
        $user = User::factory()->create();
        User::factory()->create(['username' => 'programmer']);
        User::factory()->create(['username' => 'designer']);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/users?search=programmer');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_authenticated_user_can_get_specific_user()
    {
        $user = User::factory()->create();
        $targetUser = User::factory()->create();
        $profile = UserProfile::factory()->create(['user_id' => $targetUser->id]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/users/{$targetUser->id}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $targetUser->id,
                    'username' => $targetUser->username,
                ],
            ]);
    }

    public function test_public_user_profile_endpoint()
    {
        $user = User::factory()->create();
        $profile = UserProfile::factory()->create(['user_id' => $user->id]);

        $response = $this->getJson("/api/users/{$user->id}/profile");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'profile' => [
                        'id' => $profile->id,
                        'username' => $profile->username,
                    ],
                ],
            ]);
    }

    public function test_public_user_profile_returns_404_for_nonexistent_user()
    {
        $response = $this->getJson('/api/users/999/profile');

        $response->assertStatus(404);
    }

    public function test_profile_update_creates_profile_if_not_exists()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $updateData = [
            'username' => 'newuser',
            'bio' => 'My bio',
        ];

        $response = $this->putJson('/api/profile', $updateData);

        $response->assertStatus(200);

        $this->assertDatabaseHas('user_profiles', [
            'user_id' => $user->id,
            'username' => 'newuser',
            'bio' => 'My bio',
        ]);
    }

    public function test_profile_update_handles_email_verification()
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $profile = UserProfile::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $updateData = [
            'username' => 'newuser',
            'email' => 'newemail@example.com',
        ];

        $response = $this->putJson('/api/profile', $updateData);

        $response->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'newemail@example.com',
        ]);
    }

    public function test_users_list_supports_pagination()
    {
        $user = User::factory()->create();
        User::factory(15)->create();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/users?per_page=5');

        $response->assertStatus(200);
        $this->assertCount(5, $response->json('data'));
        $this->assertEquals(16, $response->json('meta.total')); // 15 + 1 authenticated user
    }

    public function test_users_list_supports_featured_filter()
    {
        $user = User::factory()->create();
        UserProfile::factory()->create(['user_id' => User::factory()->create()->id, 'is_featured_curator' => true]);
        UserProfile::factory()->create(['user_id' => User::factory()->create()->id, 'is_featured_curator' => false]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/users?featured=true');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_authenticated_user_can_get_current_user_with_profile()
    {
        $user = User::factory()->create();
        $profile = UserProfile::factory()->create([
            'user_id' => $user->id,
            'bio' => 'Test bio',
            'website' => 'https://example.com',
            'social_links' => [
                'twitter' => 'https://twitter.com/testuser',
                'youtube' => 'https://youtube.com/testuser',
            ],
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/user');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'username',
                    'email',
                    'profile' => [
                        'id',
                        'username',
                        'bio',
                        'website',
                        'social_links',
                    ],
                ],
            ])
            ->assertJson([
                'data' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'profile' => [
                        'id' => $profile->id,
                        'bio' => 'Test bio',
                        'website' => 'https://example.com',
                        'social_links' => [
                            'twitter' => 'https://twitter.com/testuser',
                            'youtube' => 'https://youtube.com/testuser',
                        ],
                    ],
                ],
            ]);
    }

    public function test_authenticated_user_can_delete_their_account_with_correct_password()
    {
        $user = User::factory()->create([
            'password' => bcrypt('password123'),
        ]);
        $profile = UserProfile::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/user', [
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Account deleted successfully',
            ]);

        // Verify user is deleted
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('user_profiles', ['user_id' => $user->id]);
    }

    public function test_user_cannot_delete_account_with_incorrect_password()
    {
        $user = User::factory()->create([
            'password' => bcrypt('password123'),
        ]);
        $profile = UserProfile::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/user', [
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);

        // Verify user is not deleted
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseHas('user_profiles', ['user_id' => $user->id]);
    }

    public function test_user_cannot_delete_account_without_password()
    {
        $user = User::factory()->create();
        $profile = UserProfile::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/user', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);

        // Verify user is not deleted
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseHas('user_profiles', ['user_id' => $user->id]);
    }

    public function test_unauthenticated_user_cannot_delete_account()
    {
        $response = $this->deleteJson('/api/user', [
            'password' => 'password123',
        ]);

        $response->assertStatus(401);

        // Verify no users were deleted
        $this->assertDatabaseCount('users', 0);
    }

    public function test_account_deletion_invalidates_user_tokens()
    {
        $user = User::factory()->create([
            'password' => bcrypt('password123'),
        ]);
        $profile = UserProfile::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        // Create a token
        $token = $user->createToken('test-token');

        $response = $this->deleteJson('/api/user', [
            'password' => 'password123',
        ]);

        $response->assertStatus(200);

        // Verify token is invalidated
        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $token->accessToken->id,
        ]);
    }

    public function test_account_deletion_handles_related_data_cleanup()
    {
        $user = User::factory()->create([
            'password' => bcrypt('password123'),
        ]);
        $profile = UserProfile::factory()->create(['user_id' => $user->id]);

        // Create related data (collections, videos, etc.)
        $collection = \App\Models\Collection::factory()->create(['user_id' => $user->id]);
        $video = \App\Models\Video::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/user', [
            'password' => 'password123',
        ]);

        $response->assertStatus(200);

        // Verify user and related data are deleted
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('user_profiles', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('collections', ['user_id' => $user->id]);
        // Videos are not directly owned by users, so they should remain
        $this->assertDatabaseHas('videos', ['id' => $video->id]);
    }
}
