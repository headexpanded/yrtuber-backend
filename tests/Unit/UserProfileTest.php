<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_profile_can_be_created()
    {
        $user = User::factory()->create();
        $profile = UserProfile::factory()->create([
            'user_id' => $user->id,
            'username' => 'testprofile',
            'bio' => 'Test bio',
        ]);

        $this->assertDatabaseHas('user_profiles', [
            'user_id' => $user->id,
            'username' => 'testprofile',
            'bio' => 'Test bio',
        ]);
    }

    public function test_user_profile_has_user_relationship()
    {
        $user = User::factory()->create();
        $profile = UserProfile::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $profile->user);
        $this->assertEquals($user->id, $profile->user->id);
    }

    public function test_user_profile_fillable_fields()
    {
        $profileData = [
            'user_id' => User::factory()->create()->id,
            'username' => 'testprofile',
            'bio' => 'Test bio',
            'avatar' => 'avatar.jpg',
            'website' => 'https://example.com',
            'location' => 'Test City',
            'social_links' => ['twitter' => 'https://twitter.com/test'],
            'is_verified' => true,
            'is_featured_curator' => false,
            'follower_count' => 100,
            'following_count' => 50,
            'collection_count' => 10,
        ];

        $profile = UserProfile::create($profileData);

        $this->assertEquals('testprofile', $profile->username);
        $this->assertEquals('Test bio', $profile->bio);
        $this->assertTrue($profile->is_verified);
        $this->assertFalse($profile->is_featured_curator);
    }

    public function test_user_profile_casts_are_applied()
    {
        $profile = UserProfile::factory()->create([
            'social_links' => ['twitter' => 'https://twitter.com/test'],
            'is_verified' => true,
            'is_featured_curator' => false,
            'follower_count' => 100,
        ]);

        $this->assertIsArray($profile->social_links);
        $this->assertTrue($profile->is_verified);
        $this->assertFalse($profile->is_featured_curator);
        $this->assertIsInt($profile->follower_count);
    }

    public function test_user_profile_social_links_can_be_stored_as_json()
    {
        $socialLinks = [
            'twitter' => 'https://twitter.com/test',
            'youtube' => 'https://youtube.com/test',
            'instagram' => 'https://instagram.com/test',
        ];

        $profile = UserProfile::factory()->create(['social_links' => $socialLinks]);

        $this->assertEquals($socialLinks, $profile->social_links);
        $this->assertEquals('https://twitter.com/test', $profile->social_links['twitter']);
    }

    public function test_user_profile_username_is_unique()
    {
        UserProfile::factory()->create(['username' => 'testuser']);

        $this->expectException(\Illuminate\Database\QueryException::class);

        UserProfile::factory()->create(['username' => 'testuser']);
    }

    public function test_user_profile_has_default_values()
    {
        $profile = UserProfile::factory()->withDefaults()->create();

        $this->assertFalse($profile->is_verified);
        $this->assertFalse($profile->is_featured_curator);
        $this->assertEquals(0, $profile->follower_count);
    }

    public function test_user_profile_can_be_updated()
    {
        $profile = UserProfile::factory()->create(['bio' => 'Original bio']);

        $profile->update(['bio' => 'Updated bio']);

        $this->assertEquals('Updated bio', $profile->fresh()->bio);
    }

    public function test_user_profile_deletion_cascades_from_user()
    {
        $user = User::factory()->create();
        $profile = UserProfile::factory()->create(['user_id' => $user->id]);

        $user->delete();

        $this->assertDatabaseMissing('user_profiles', ['id' => $profile->id]);
    }
}
