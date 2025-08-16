<?php

namespace Tests\Unit;

use App\Http\Requests\ShareCollectionRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ShareCollectionRequestTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test valid share collection data passes validation
     */
    public function test_valid_share_collection_data_passes_validation(): void
    {
        $data = [
            'platform' => 'twitter',
            'share_type' => 'public',
            'expires_at' => now()->addDays(7)->toISOString(),
            'custom_url' => 'https://example.com/custom',
        ];

        $request = new ShareCollectionRequest();
        $rules = $request->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->passes());
    }

    /**
     * Test platform is required
     */
    public function test_platform_is_required(): void
    {
        $data = [
            'share_type' => 'public',
        ];

        $request = new ShareCollectionRequest();
        $rules = $request->rules();
        $validator = Validator::make($data, $rules);

        $this->assertFalse($validator->passes());
        $this->assertTrue($validator->errors()->has('platform'));
    }

    /**
     * Test platform must be valid value
     */
    public function test_platform_must_be_valid_value(): void
    {
        $data = [
            'platform' => 'invalid_platform',
        ];

        $request = new ShareCollectionRequest();
        $rules = $request->rules();
        $validator = Validator::make($data, $rules);

        $this->assertFalse($validator->passes());
        $this->assertTrue($validator->errors()->has('platform'));
    }

    /**
     * Test all valid platforms are accepted
     */
    public function test_all_valid_platforms_are_accepted(): void
    {
        $validPlatforms = ['twitter', 'facebook', 'linkedin', 'email', 'link'];

        foreach ($validPlatforms as $platform) {
            $data = ['platform' => $platform];
            $request = new ShareCollectionRequest();
            $rules = $request->rules();
            $validator = Validator::make($data, $rules);

            $this->assertTrue($validator->passes(), "Platform '{$platform}' should be valid");
        }
    }

    /**
     * Test share type must be valid value
     */
    public function test_share_type_must_be_valid_value(): void
    {
        $data = [
            'platform' => 'twitter',
            'share_type' => 'invalid_type',
        ];

        $request = new ShareCollectionRequest();
        $rules = $request->rules();
        $validator = Validator::make($data, $rules);

        $this->assertFalse($validator->passes());
        $this->assertTrue($validator->errors()->has('share_type'));
    }

    /**
     * Test all valid share types are accepted
     */
    public function test_all_valid_share_types_are_accepted(): void
    {
        $validTypes = ['public', 'private', 'temporary'];

        foreach ($validTypes as $type) {
            $data = [
                'platform' => 'twitter',
                'share_type' => $type,
            ];
            $request = new ShareCollectionRequest();
            $rules = $request->rules();
            $validator = Validator::make($data, $rules);

            $this->assertTrue($validator->passes(), "Share type '{$type}' should be valid");
        }
    }

    /**
     * Test expires_at must be future date
     */
    public function test_expires_at_must_be_future_date(): void
    {
        $data = [
            'platform' => 'twitter',
            'expires_at' => now()->subDays(1)->toISOString(),
        ];

        $request = new ShareCollectionRequest();
        $rules = $request->rules();
        $validator = Validator::make($data, $rules);

        $this->assertFalse($validator->passes());
        $this->assertTrue($validator->errors()->has('expires_at'));
    }

    /**
     * Test expires_at can be null
     */
    public function test_expires_at_can_be_null(): void
    {
        $data = [
            'platform' => 'twitter',
            'expires_at' => null,
        ];

        $request = new ShareCollectionRequest();
        $rules = $request->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->passes());
    }

    /**
     * Test custom_url must be valid URL
     */
    public function test_custom_url_must_be_valid_url(): void
    {
        $data = [
            'platform' => 'twitter',
            'custom_url' => 'not_a_url',
        ];

        $request = new ShareCollectionRequest();
        $rules = $request->rules();
        $validator = Validator::make($data, $rules);

        $this->assertFalse($validator->passes());
        $this->assertTrue($validator->errors()->has('custom_url'));
    }

    /**
     * Test custom_url can be null
     */
    public function test_custom_url_can_be_null(): void
    {
        $data = [
            'platform' => 'twitter',
            'custom_url' => null,
        ];

        $request = new ShareCollectionRequest();
        $rules = $request->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->passes());
    }

    /**
     * Test custom error messages
     */
    public function test_custom_error_messages(): void
    {
        $request = new ShareCollectionRequest();
        $messages = $request->messages();

        $this->assertEquals('Please select a sharing platform.', $messages['platform.required']);
        $this->assertEquals('The selected platform is not supported.', $messages['platform.in']);
        $this->assertEquals('The selected share type is not valid.', $messages['share_type.in']);
        $this->assertEquals('The expiration date must be in the future.', $messages['expires_at.after']);
        $this->assertEquals('Please provide a valid URL.', $messages['custom_url.url']);
    }

    /**
     * Test custom attributes
     */
    public function test_custom_attributes(): void
    {
        $request = new ShareCollectionRequest();
        $attributes = $request->attributes();

        $this->assertEquals('sharing platform', $attributes['platform']);
        $this->assertEquals('share type', $attributes['share_type']);
        $this->assertEquals('expiration date', $attributes['expires_at']);
        $this->assertEquals('custom URL', $attributes['custom_url']);
    }
}
