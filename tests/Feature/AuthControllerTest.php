<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_refresh_session()
    {
        $user = User::factory()->create();
        $profile = UserProfile::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/auth/refresh-session');

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Session refreshed successfully',
                'user' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'email' => $user->email,
                ]
            ])
            ->assertJsonStructure([
                'message',
                'user' => [
                    'id',
                    'username',
                    'email',
                    'profile'
                ]
            ]);
    }

    public function test_unauthenticated_user_cannot_refresh_session()
    {
        $response = $this->postJson('/api/auth/refresh-session');

        $response->assertStatus(401)
            ->assertJson(['message' => 'Unauthenticated.']);
    }

    public function test_authenticated_user_can_check_auth_status()
    {
        $user = User::factory()->create();
        $profile = UserProfile::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/auth/check');

        $response->assertStatus(200)
            ->assertJson([
                'authenticated' => true,
                'user' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'email' => $user->email,
                ]
            ])
            ->assertJsonStructure([
                'authenticated',
                'user' => [
                    'id',
                    'username',
                    'email',
                    'profile'
                ]
            ]);
    }

    public function test_unauthenticated_user_gets_false_auth_status()
    {
        $response = $this->getJson('/api/auth/check');

        $response->assertStatus(401)
            ->assertJson(['message' => 'Unauthenticated.']);
    }
}
