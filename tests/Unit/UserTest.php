<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\UserProfile;
use App\Models\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_be_created()
    {
        $user = User::factory()->create([
            'username' => 'testuser',
            'email' => 'test@example.com',
        ]);

        $this->assertDatabaseHas('users', [
            'username' => 'testuser',
            'email' => 'test@example.com',
        ]);
    }

    public function test_user_has_profile_relationship()
    {
        $user = User::factory()->create();
        $profile = UserProfile::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(UserProfile::class, $user->profile);
        $this->assertEquals($profile->id, $user->profile->id);
    }

    public function test_user_has_collections_relationship()
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->create(['user_id' => $user->id]);

        $this->assertCount(1, $user->collections);
        $this->assertInstanceOf(Collection::class, $user->collections->first());
    }

    public function test_user_fillable_fields()
    {
        $userData = [
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => 'password',
        ];

        $user = User::create($userData);

        $this->assertEquals('testuser', $user->username);
        $this->assertEquals('test@example.com', $user->email);
    }

    public function test_user_hidden_fields_are_not_serialized()
    {
        $user = User::factory()->create();
        $userArray = $user->toArray();

        $this->assertArrayNotHasKey('password', $userArray);
        $this->assertArrayNotHasKey('remember_token', $userArray);
    }

    public function test_user_casts_are_applied()
    {
        $user = User::factory()->create();

        $this->assertInstanceOf(\Carbon\Carbon::class, $user->email_verified_at);
        $this->assertInstanceOf(\Carbon\Carbon::class, $user->created_at);
        $this->assertInstanceOf(\Carbon\Carbon::class, $user->updated_at);
    }

    public function test_user_implements_must_verify_email()
    {
        $user = User::factory()->create();

        $this->assertInstanceOf(\Illuminate\Contracts\Auth\MustVerifyEmail::class, $user);
    }

    public function test_user_has_api_tokens_trait()
    {
        $user = User::factory()->create();

        $this->assertTrue(method_exists($user, 'tokens'));
        $this->assertTrue(method_exists($user, 'createToken'));
    }
}
