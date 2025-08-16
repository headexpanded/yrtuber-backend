<?php

namespace Tests\Unit;

use App\Http\Requests\NotificationRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class NotificationRequestTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test valid notification request data passes validation
     */
    public function test_valid_notification_request_data_passes_validation(): void
    {
        $data = [
            'per_page' => 20,
            'type' => 'collection_liked',
            'read' => false,
        ];

        $request = new NotificationRequest();
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

        $request = new NotificationRequest();
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

        $request = new NotificationRequest();
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

        $request = new NotificationRequest();
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
            $request = new NotificationRequest();
            $rules = $request->rules();
            $validator = Validator::make($data, $rules);

            $this->assertTrue($validator->passes(), "Per page value '{$value}' should be valid");
        }
    }

    /**
     * Test type must be string
     */
    public function test_type_must_be_string(): void
    {
        $data = [
            'type' => 123,
        ];

        $request = new NotificationRequest();
        $rules = $request->rules();
        $validator = Validator::make($data, $rules);

        $this->assertFalse($validator->passes());
        $this->assertTrue($validator->errors()->has('type'));
    }

    /**
     * Test type can be null
     */
    public function test_type_can_be_null(): void
    {
        $data = [
            'type' => null,
        ];

        $request = new NotificationRequest();
        $rules = $request->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->passes());
    }

    /**
     * Test read must be boolean
     */
    public function test_read_must_be_boolean(): void
    {
        $data = [
            'read' => 'not_a_boolean',
        ];

        $request = new NotificationRequest();
        $rules = $request->rules();
        $validator = Validator::make($data, $rules);

        $this->assertFalse($validator->passes());
        $this->assertTrue($validator->errors()->has('read'));
    }

    /**
     * Test read can be null
     */
    public function test_read_can_be_null(): void
    {
        $data = [
            'read' => null,
        ];

        $request = new NotificationRequest();
        $rules = $request->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->passes());
    }

    /**
     * Test boolean values for read are accepted
     */
    public function test_boolean_values_for_read_are_accepted(): void
    {
        $booleanValues = [true, false];

        foreach ($booleanValues as $value) {
            $data = ['read' => $value];
            $request = new NotificationRequest();
            $rules = $request->rules();
            $validator = Validator::make($data, $rules);

            $this->assertTrue($validator->passes(), "Read value '{$value}' should be valid");
        }
    }

    /**
     * Test custom error messages
     */
    public function test_custom_error_messages(): void
    {
        $request = new NotificationRequest();
        $messages = $request->messages();

        $this->assertEquals('The per page value must be at least 1.', $messages['per_page.min']);
        $this->assertEquals('The per page value may not be greater than 100.', $messages['per_page.max']);
        $this->assertEquals('The notification type must be a string.', $messages['type.string']);
        $this->assertEquals('The read status must be true or false.', $messages['read.boolean']);
    }

    /**
     * Test custom attributes
     */
    public function test_custom_attributes(): void
    {
        $request = new NotificationRequest();
        $attributes = $request->attributes();

        $this->assertEquals('per page', $attributes['per_page']);
        $this->assertEquals('notification type', $attributes['type']);
        $this->assertEquals('read status', $attributes['read']);
    }

    /**
     * Test empty data passes validation (all fields are optional)
     */
    public function test_empty_data_passes_validation(): void
    {
        $data = [];

        $request = new NotificationRequest();
        $rules = $request->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->passes());
    }
}
