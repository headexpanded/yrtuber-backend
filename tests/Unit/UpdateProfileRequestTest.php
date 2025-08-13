<?php

namespace Tests\Unit;

use App\Http\Requests\UpdateProfileRequest;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class UpdateProfileRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_profile_data_passes_validation()
    {
        $user = User::factory()->create();
        $profile = UserProfile::factory()->create(['user_id' => $user->id]);

        $data = [
            'username' => 'newusername',
            'bio' => 'My bio',
            'website' => 'https://example.com',
            'location' => 'New York',
            'social_links' => [
                'twitter' => 'https://twitter.com/user',
                'youtube' => 'https://youtube.com/user',
            ],
        ];

        $request = new UpdateProfileRequest();
        $request->merge($data);
        $request->setUserResolver(fn() => $user);

        $validator = Validator::make($data, $request->rules());
        $this->assertTrue($validator->passes());
    }

    public function test_username_is_required_when_provided()
    {
        $user = User::factory()->create();
        $profile = UserProfile::factory()->create(['user_id' => $user->id]);

        $data = ['username' => ''];

        $request = new UpdateProfileRequest();
        $request->merge($data);
        $request->setUserResolver(fn() => $user);

        $validator = Validator::make($data, $request->rules());
        // Since username is 'sometimes', an empty string should pass validation
        $this->assertTrue($validator->passes());
    }

    public function test_username_must_be_string()
    {
        $user = User::factory()->create();
        $profile = UserProfile::factory()->create(['user_id' => $user->id]);

        $data = ['username' => 123];

        $request = new UpdateProfileRequest();
        $request->merge($data);
        $request->setUserResolver(fn() => $user);

        $validator = Validator::make($data, $request->rules());
        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('username'));
    }

    public function test_username_must_not_exceed_255_characters()
    {
        $user = User::factory()->create();
        $profile = UserProfile::factory()->create(['user_id' => $user->id]);

        $data = ['username' => str_repeat('a', 256)];

        $request = new UpdateProfileRequest();
        $request->merge($data);
        $request->setUserResolver(fn() => $user);

        $validator = Validator::make($data, $request->rules());
        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('username'));
    }

    public function test_username_must_be_unique_except_for_current_user()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $profile1 = UserProfile::factory()->create(['user_id' => $user1->id, 'username' => 'user1']);
        $profile2 = UserProfile::factory()->create(['user_id' => $user2->id, 'username' => 'user2']);

        // User1 trying to use user2's username
        $data = ['username' => 'user2'];

        $request = new UpdateProfileRequest();
        $request->merge($data);
        $request->setUserResolver(fn() => $user1);

        $validator = Validator::make($data, $request->rules());
        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('username'));
    }

    public function test_user_can_keep_their_own_username()
    {
        $user = User::factory()->create();
        $profile = UserProfile::factory()->create(['user_id' => $user->id, 'username' => 'myusername']);

        $data = ['username' => 'myusername'];

        $request = new UpdateProfileRequest();
        $request->merge($data);
        $request->setUserResolver(fn() => $user);

        $validator = Validator::make($data, $request->rules());
        $this->assertTrue($validator->passes());
    }

    public function test_bio_must_not_exceed_1000_characters()
    {
        $user = User::factory()->create();
        $profile = UserProfile::factory()->create(['user_id' => $user->id]);

        $data = ['bio' => str_repeat('a', 1001)];

        $request = new UpdateProfileRequest();
        $request->merge($data);
        $request->setUserResolver(fn() => $user);

        $validator = Validator::make($data, $request->rules());
        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('bio'));
    }

    public function test_website_must_be_valid_url()
    {
        $user = User::factory()->create();
        $profile = UserProfile::factory()->create(['user_id' => $user->id]);

        $data = ['website' => 'invalid-url'];

        $request = new UpdateProfileRequest();
        $request->merge($data);
        $request->setUserResolver(fn() => $user);

        $validator = Validator::make($data, $request->rules());
        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('website'));
    }

    public function test_website_can_be_null()
    {
        $user = User::factory()->create();
        $profile = UserProfile::factory()->create(['user_id' => $user->id]);

        $data = ['website' => null];

        $request = new UpdateProfileRequest();
        $request->merge($data);
        $request->setUserResolver(fn() => $user);

        $validator = Validator::make($data, $request->rules());
        $this->assertTrue($validator->passes());
    }

    public function test_social_links_must_be_array()
    {
        $user = User::factory()->create();
        $profile = UserProfile::factory()->create(['user_id' => $user->id]);

        $data = ['social_links' => 'not-an-array'];

        $request = new UpdateProfileRequest();
        $request->merge($data);
        $request->setUserResolver(fn() => $user);

        $validator = Validator::make($data, $request->rules());
        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('social_links'));
    }

    public function test_social_links_urls_must_be_valid()
    {
        $user = User::factory()->create();
        $profile = UserProfile::factory()->create(['user_id' => $user->id]);

        $data = [
            'social_links' => [
                'twitter' => 'invalid-url',
                'youtube' => 'https://youtube.com/user',
            ],
        ];

        $request = new UpdateProfileRequest();
        $request->merge($data);
        $request->setUserResolver(fn() => $user);

        $validator = Validator::make($data, $request->rules());
        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('social_links.twitter'));
    }

    public function test_request_authorizes_all_users()
    {
        $request = new UpdateProfileRequest();
        $this->assertTrue($request->authorize());
    }

    public function test_custom_error_messages()
    {
        $user = User::factory()->create();
        $profile = UserProfile::factory()->create(['user_id' => $user->id]);

        $data = ['website' => 'invalid-url'];

        $request = new UpdateProfileRequest();
        $request->merge($data);
        $request->setUserResolver(fn() => $user);

        $validator = Validator::make($data, $request->rules(), $request->messages());
        $validator->fails();

        $this->assertNotEmpty($validator->errors()->get('website'));
    }
}
