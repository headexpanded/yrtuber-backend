<?php

namespace Tests\Unit;

use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class UserResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_resource_transforms_user_data()
    {
        $user = User::factory()->create([
            'username' => 'testuser',
            'email' => 'test@example.com',
        ]);

        $resource = new UserResource($user);
        $data = $resource->toArray(new Request());

        $this->assertEquals($user->id, $data['id']);
        $this->assertEquals('testuser', $data['username']);
        $this->assertEquals('test@example.com', $data['email']);
        $this->assertArrayHasKey('created_at', $data);
        $this->assertArrayHasKey('updated_at', $data);
    }

    public function test_user_resource_includes_profile_when_loaded()
    {
        $user = User::factory()->create();
        $profile = UserProfile::factory()->create([
            'user_id' => $user->id,
            'username' => 'profileuser',
            'bio' => 'Test bio',
            'website' => 'https://example.com',
        ]);

        $user->load('profile');
        $resource = new UserResource($user);
        $data = $resource->toArray(new Request());

        $this->assertArrayHasKey('profile', $data);
        $this->assertEquals($profile->id, $data['profile']['id']);
        $this->assertEquals('profileuser', $data['profile']['username']);
        $this->assertEquals('Test bio', $data['profile']['bio']);
        $this->assertEquals('https://example.com', $data['profile']['website']);
    }

    public function test_user_resource_excludes_profile_when_not_loaded()
    {
        $user = User::factory()->create();
        // Don't create a profile for this user

        $resource = new UserResource($user);
        $data = $resource->toArray(new Request());

        $this->assertArrayNotHasKey('profile', $data);
    }

    public function test_user_resource_handles_null_profile()
    {
        $user = User::factory()->create();
        // Don't create a profile for this user

        $resource = new UserResource($user);
        $data = $resource->toArray(new Request());

        $this->assertArrayNotHasKey('profile', $data);
    }



    public function test_user_resource_includes_all_profile_fields()
    {
        $user = User::factory()->create();
        $profile = UserProfile::factory()->create([
            'user_id' => $user->id,
            'username' => 'testuser',
            'bio' => 'Test bio',
            'avatar' => 'avatar.jpg',
            'website' => 'https://example.com',
            'location' => 'New York',
            'social_links' => ['twitter' => 'https://twitter.com/user'],
            'is_verified' => true,
            'is_featured_curator' => false,
            'follower_count' => 100,
            'following_count' => 50,
            'collection_count' => 10,
        ]);

        $user->load('profile');
        $resource = new UserResource($user);
        $data = $resource->toArray(new Request());

        $profileData = $data['profile'];
        $this->assertEquals($profile->id, $profileData['id']);
        $this->assertEquals('testuser', $profileData['username']);
        $this->assertEquals('Test bio', $profileData['bio']);
        $this->assertEquals('avatar.jpg', $profileData['avatar']);
        $this->assertEquals('https://example.com', $profileData['website']);
        $this->assertEquals('New York', $profileData['location']);
        $this->assertEquals(['twitter' => 'https://twitter.com/user'], $profileData['social_links']);
        $this->assertTrue($profileData['is_verified']);
        $this->assertFalse($profileData['is_featured_curator']);
        $this->assertEquals(100, $profileData['follower_count']);
        $this->assertEquals(50, $profileData['following_count']);
        $this->assertEquals(10, $profileData['collection_count']);
        $this->assertArrayHasKey('created_at', $profileData);
        $this->assertArrayHasKey('updated_at', $profileData);
    }

    public function test_user_resource_handles_email_verification()
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $resource = new UserResource($user);
        $data = $resource->toArray(new Request());

        $this->assertNotNull($data['email_verified_at']);
        $this->assertInstanceOf(\Carbon\Carbon::class, $data['email_verified_at']);
    }

    public function test_user_resource_handles_unverified_email()
    {
        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);

        $resource = new UserResource($user);
        $data = $resource->toArray(new Request());

        $this->assertNull($data['email_verified_at']);
    }

    public function test_user_resource_collection()
    {
        $users = User::factory(3)->create();

        $collection = UserResource::collection($users);
        $data = $collection->toArray(new Request());

        $this->assertCount(3, $data);
        $this->assertEquals($users[0]->id, $data[0]['id']);
        $this->assertEquals($users[1]->id, $data[1]['id']);
        $this->assertEquals($users[2]->id, $data[2]['id']);
    }

    public function test_user_resource_with_additional_context()
    {
        $user = User::factory()->create();
        $request = new Request();
        $request->merge(['include_profile' => true]);

        $resource = new UserResource($user);
        $data = $resource->toArray($request);

        // The resource should still work normally even with additional context
        $this->assertEquals($user->id, $data['id']);
        $this->assertEquals($user->username, $data['username']);
    }

    public function test_user_resource_json_serialization()
    {
        $user = User::factory()->create([
            'username' => 'testuser',
            'email' => 'test@example.com',
        ]);

        $resource = new UserResource($user);
        $json = json_encode($resource);

        $this->assertIsString($json);
        $this->assertStringContainsString('testuser', $json);
        $this->assertStringContainsString('test@example.com', $json);
    }

    public function test_user_resource_with_complex_social_links()
    {
        $user = User::factory()->create();
        $profile = UserProfile::factory()->create([
            'user_id' => $user->id,
            'social_links' => [
                'twitter' => 'https://twitter.com/user',
                'youtube' => 'https://youtube.com/user',
                'instagram' => 'https://instagram.com/user',
                'tiktok' => 'https://tiktok.com/user',
            ],
        ]);

        $user->load('profile');
        $resource = new UserResource($user);
        $data = $resource->toArray(new Request());

        $socialLinks = $data['profile']['social_links'];
        $this->assertEquals('https://twitter.com/user', $socialLinks['twitter']);
        $this->assertEquals('https://youtube.com/user', $socialLinks['youtube']);
        $this->assertEquals('https://instagram.com/user', $socialLinks['instagram']);
        $this->assertEquals('https://tiktok.com/user', $socialLinks['tiktok']);
    }

    public function test_user_resource_handles_empty_social_links()
    {
        $user = User::factory()->create();
        $profile = UserProfile::factory()->create([
            'user_id' => $user->id,
            'social_links' => [],
        ]);

        $user->load('profile');
        $resource = new UserResource($user);
        $data = $resource->toArray(new Request());

        $this->assertEquals([], $data['profile']['social_links']);
    }

    public function test_user_resource_handles_null_social_links()
    {
        $user = User::factory()->create();
        $profile = UserProfile::factory()->create([
            'user_id' => $user->id,
            'social_links' => null,
        ]);

        $user->load('profile');
        $resource = new UserResource($user);
        $data = $resource->toArray(new Request());

        $this->assertNull($data['profile']['social_links']);
    }
}
