<?php

namespace Tests\Unit;

use App\Http\Requests\ActivityFeedRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ActivityFeedRequestTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test valid activity feed request data passes validation
     */
    public function test_valid_activity_feed_request_data_passes_validation(): void
    {
        $data = [
            'per_page' => 20,
            'feed_type' => 'personalized',
            'action' => 'collection.liked',
            'subject_type' => 'App\Models\Collection',
            'user_id' => null,
        ];

        $request = new ActivityFeedRequest();
        $rules = $request->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->passes());
    }

    /**
     * Test per_page minimum value validation
     */
    public function test_per_page_minimum_value_validation(): void
    {
        $data = [
            'per_page' => 0,
        ];

        $request = new ActivityFeedRequest();
        $rules = $request->rules();
        $validator = Validator::make($data, $rules);

        $this->assertFalse($validator->passes());
        $this->assertTrue($validator->errors()->has('per_page'));
    }

    /**
     * Test per_page maximum value validation
     */
    public function test_per_page_maximum_value_validation(): void
    {
        $data = [
            'per_page' => 150,
        ];

        $request = new ActivityFeedRequest();
        $rules = $request->rules();
        $validator = Validator::make($data, $rules);

        $this->assertFalse($validator->passes());
        $this->assertTrue($validator->errors()->has('per_page'));
    }

    /**
     * Test per_page must be integer
     */
    public function test_per_page_must_be_integer(): void
    {
        $data = [
            'per_page' => 'not_an_integer',
        ];

        $request = new ActivityFeedRequest();
        $rules = $request->rules();
        $validator = Validator::make($data, $rules);

        $this->assertFalse($validator->passes());
        $this->assertTrue($validator->errors()->has('per_page'));
    }

    /**
     * Test valid per_page values are accepted
     */
    public function test_valid_per_page_values_are_accepted(): void
    {
        $validValues = [1, 10, 25, 50, 100];

        foreach ($validValues as $value) {
            $data = ['per_page' => $value];
            $request = new ActivityFeedRequest();
            $rules = $request->rules();
            $validator = Validator::make($data, $rules);

            $this->assertTrue($validator->passes(), "Per page value '{$value}' should be valid");
        }
    }

    /**
     * Test feed_type must be valid value
     */
    public function test_feed_type_must_be_valid_value(): void
    {
        $data = [
            'feed_type' => 'invalid_feed_type',
        ];

        $request = new ActivityFeedRequest();
        $rules = $request->rules();
        $validator = Validator::make($data, $rules);

        $this->assertFalse($validator->passes());
        $this->assertTrue($validator->errors()->has('feed_type'));
    }

    /**
     * Test all valid feed types are accepted
     */
    public function test_all_valid_feed_types_are_accepted(): void
    {
        $validTypes = ['personalized', 'global', 'user', 'targeted'];

        foreach ($validTypes as $type) {
            $data = ['feed_type' => $type];
            $request = new ActivityFeedRequest();
            $rules = $request->rules();
            $validator = Validator::make($data, $rules);

            $this->assertTrue($validator->passes(), "Feed type '{$type}' should be valid");
        }
    }

    /**
     * Test action must be string
     */
    public function test_action_must_be_string(): void
    {
        $data = [
            'action' => 123,
        ];

        $request = new ActivityFeedRequest();
        $rules = $request->rules();
        $validator = Validator::make($data, $rules);

        $this->assertFalse($validator->passes());
        $this->assertTrue($validator->errors()->has('action'));
    }

    /**
     * Test action can be null
     */
    public function test_action_can_be_null(): void
    {
        $data = [
            'action' => null,
        ];

        $request = new ActivityFeedRequest();
        $rules = $request->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->passes());
    }

    /**
     * Test subject_type must be string
     */
    public function test_subject_type_must_be_string(): void
    {
        $data = [
            'subject_type' => 123,
        ];

        $request = new ActivityFeedRequest();
        $rules = $request->rules();
        $validator = Validator::make($data, $rules);

        $this->assertFalse($validator->passes());
        $this->assertTrue($validator->errors()->has('subject_type'));
    }

    /**
     * Test subject_type can be null
     */
    public function test_subject_type_can_be_null(): void
    {
        $data = [
            'subject_type' => null,
        ];

        $request = new ActivityFeedRequest();
        $rules = $request->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->passes());
    }

    /**
     * Test user_id must be integer
     */
    public function test_user_id_must_be_integer(): void
    {
        $data = [
            'user_id' => 'not_an_integer',
        ];

        $request = new ActivityFeedRequest();
        $rules = $request->rules();
        $validator = Validator::make($data, $rules);

        $this->assertFalse($validator->passes());
        $this->assertTrue($validator->errors()->has('user_id'));
    }

    /**
     * Test user_id can be null
     */
    public function test_user_id_can_be_null(): void
    {
        $data = [
            'user_id' => null,
        ];

        $request = new ActivityFeedRequest();
        $rules = $request->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->passes());
    }

    /**
     * Test user_id must exist in users table
     */
    public function test_user_id_must_exist_in_users_table(): void
    {
        $data = [
            'user_id' => 99999, // Non-existent user ID
        ];

        $request = new ActivityFeedRequest();
        $rules = $request->rules();
        $validator = Validator::make($data, $rules);

        $this->assertFalse($validator->passes());
        $this->assertTrue($validator->errors()->has('user_id'));
    }

    /**
     * Test custom error messages
     */
    public function test_custom_error_messages(): void
    {
        $request = new ActivityFeedRequest();
        $messages = $request->messages();

        $this->assertEquals('The per page value must be at least 1.', $messages['per_page.min']);
        $this->assertEquals('The per page value may not be greater than 100.', $messages['per_page.max']);
        $this->assertEquals('The selected feed type is not valid.', $messages['feed_type.in']);
        $this->assertEquals('The action must be a string.', $messages['action.string']);
        $this->assertEquals('The subject type must be a string.', $messages['subject_type.string']);
        $this->assertEquals('The selected user does not exist.', $messages['user_id.exists']);
    }

    /**
     * Test custom attributes
     */
    public function test_custom_attributes(): void
    {
        $request = new ActivityFeedRequest();
        $attributes = $request->attributes();

        $this->assertEquals('per page', $attributes['per_page']);
        $this->assertEquals('feed type', $attributes['feed_type']);
        $this->assertEquals('action', $attributes['action']);
        $this->assertEquals('subject type', $attributes['subject_type']);
        $this->assertEquals('user', $attributes['user_id']);
    }

    /**
     * Test empty data passes validation (all fields are optional)
     */
    public function test_empty_data_passes_validation(): void
    {
        $data = [];

        $request = new ActivityFeedRequest();
        $rules = $request->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->passes());
    }
}
