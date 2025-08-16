<?php

namespace Tests\Unit;

use App\Http\Resources\ActivityLogResource;
use App\Models\ActivityLog;
use App\Models\User;
use App\Models\Collection;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogResourceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $targetUser;
    private Collection $collection;
    private ActivityLog $activityLog;

    protected function setUp(): void
    {
        parent::setUp();

        // Create users with profiles
        $this->user = User::factory()->create();
        $this->targetUser = User::factory()->create();

        UserProfile::factory()->create(['user_id' => $this->user->id]);
        UserProfile::factory()->create(['user_id' => $this->targetUser->id]);

        // Create a collection
        $this->collection = Collection::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Test Collection',
        ]);

        // Create an activity log
        $this->activityLog = ActivityLog::create([
            'user_id' => $this->user->id,
            'action' => 'collection.liked',
            'subject_type' => Collection::class,
            'subject_id' => $this->collection->id,
            'target_user_id' => $this->targetUser->id,
            'properties' => [
                'subject_title' => 'Test Collection',
                'subject_type' => 'Collection',
            ],
            'visibility' => 'public',
            'aggregated_count' => 1,
        ]);
    }

    /**
     * Test activity log resource transforms data correctly
     */
    public function test_activity_log_resource_transforms_data_correctly(): void
    {
        $resource = new ActivityLogResource($this->activityLog);
        $data = $resource->toArray(request());

        $this->assertEquals($this->activityLog->id, $data['id']);
        $this->assertEquals('collection.liked', $data['action']);
        $this->assertEquals($this->activityLog->properties, $data['properties']);
        $this->assertEquals('public', $data['visibility']);
        $this->assertEquals(1, $data['aggregated_count']);
        $this->assertNotNull($data['created_at']);
        $this->assertNotNull($data['updated_at']);
    }

    /**
     * Test activity log resource includes user information when loaded
     */
    public function test_activity_log_resource_includes_user_information_when_loaded(): void
    {
        $this->activityLog->load('user.profile');
        $resource = new ActivityLogResource($this->activityLog);
        $data = $resource->toArray(request());

        $this->assertArrayHasKey('user', $data);
        $this->assertEquals($this->user->id, $data['user']['id']);
        $this->assertEquals($this->user->username, $data['user']['username']);
        $this->assertEquals($this->user->email, $data['user']['email']);
        $this->assertArrayHasKey('profile', $data['user']);
    }

    /**
     * Test activity log resource includes target user information when loaded
     */
    public function test_activity_log_resource_includes_target_user_information_when_loaded(): void
    {
        $this->activityLog->load('targetUser.profile');
        $resource = new ActivityLogResource($this->activityLog);
        $data = $resource->toArray(request());

        $this->assertArrayHasKey('target_user', $data);
        $this->assertEquals($this->targetUser->id, $data['target_user']['id']);
        $this->assertEquals($this->targetUser->username, $data['target_user']['username']);
        $this->assertEquals($this->targetUser->email, $data['target_user']['email']);
        $this->assertArrayHasKey('profile', $data['target_user']);
    }

    /**
     * Test activity log resource handles null target user gracefully
     */
    public function test_activity_log_resource_handles_null_target_user_gracefully(): void
    {
        $this->activityLog->target_user_id = null;
        $this->activityLog->load('targetUser.profile');

        $resource = new ActivityLogResource($this->activityLog);
        $data = $resource->toArray(request());

        $this->assertNull($data['target_user']);
    }

    /**
     * Test activity log resource includes subject information when loaded
     */
    public function test_activity_log_resource_includes_subject_information_when_loaded(): void
    {
        $this->activityLog->load('subject');
        $resource = new ActivityLogResource($this->activityLog);
        $data = $resource->toArray(request());

        $this->assertArrayHasKey('subject', $data);
        $this->assertEquals($this->collection->id, $data['subject']['id']);
        $this->assertEquals('App\Models\Collection', $data['subject']['type']);
        $this->assertEquals('Test Collection', $data['subject']['title']);
        $this->assertNotNull($data['subject']['url']);
    }

    /**
     * Test activity log resource handles null subject gracefully
     */
    public function test_activity_log_resource_handles_null_subject_gracefully(): void
    {
        $this->activityLog->subject_type = 'NonExistentModel';
        $this->activityLog->subject_id = 999;

        $resource = new ActivityLogResource($this->activityLog);
        $data = $resource->toArray(request());

        $this->assertNull($data['subject']);
    }

    /**
     * Test activity log resource includes time_ago
     */
    public function test_activity_log_resource_includes_time_ago(): void
    {
        $resource = new ActivityLogResource($this->activityLog);
        $data = $resource->toArray(request());

        $this->assertArrayHasKey('time_ago', $data);
        $this->assertIsString($data['time_ago']);
    }

    /**
     * Test activity log resource formats action correctly
     */
    public function test_activity_log_resource_formats_action_correctly(): void
    {
        $resource = new ActivityLogResource($this->activityLog);
        $data = $resource->toArray(request());

        $this->assertEquals('Liked Collection', $data['formatted_action']);
    }

    /**
     * Test activity log resource handles unknown actions gracefully
     */
    public function test_activity_log_resource_handles_unknown_actions_gracefully(): void
    {
        $this->activityLog->action = 'unknown.action';

        $resource = new ActivityLogResource($this->activityLog);
        $data = $resource->toArray(request());

        $this->assertEquals('Unknown Action', $data['formatted_action']);
    }

    /**
     * Test activity log resource computes is_aggregated correctly
     */
    public function test_activity_log_resource_computes_is_aggregated_correctly(): void
    {
        $resource = new ActivityLogResource($this->activityLog);
        $data = $resource->toArray(request());

        $this->assertFalse($data['is_aggregated']);

        // Set aggregated count
        $this->activityLog->update(['aggregated_count' => 5]);
        $resource = new ActivityLogResource($this->activityLog);
        $data = $resource->toArray(request());

        $this->assertTrue($data['is_aggregated']);
    }

    /**
     * Test activity log resource includes other_users
     */
    public function test_activity_log_resource_includes_other_users(): void
    {
        $this->activityLog->properties = [
            'subject_title' => 'Test Collection',
            'subject_type' => 'Collection',
            'other_users' => ['user1', 'user2'],
        ];
        $this->activityLog->save();

        $resource = new ActivityLogResource($this->activityLog);
        $data = $resource->toArray(request());

        $this->assertArrayHasKey('other_users', $data);
        $this->assertEquals(['user1', 'user2'], $data['other_users']);
    }

    /**
     * Test activity log resource generates correct subject URLs
     */
    public function test_activity_log_resource_generates_correct_subject_urls(): void
    {
        $this->activityLog->load('subject');
        $resource = new ActivityLogResource($this->activityLog);
        $data = $resource->toArray(request());

        $expectedUrl = url("/collections/{$this->collection->slug}");
        $this->assertEquals($expectedUrl, $data['subject']['url']);
    }

    /**
     * Test activity log resource handles different subject types
     */
    public function test_activity_log_resource_handles_different_subject_types(): void
    {
        // Test with Video
        $video = \App\Models\Video::factory()->create();
        $this->activityLog->subject_type = \App\Models\Video::class;
        $this->activityLog->subject_id = $video->id;
        $this->activityLog->load('subject');

        $resource = new ActivityLogResource($this->activityLog);
        $data = $resource->toArray(request());

        $expectedUrl = url("/videos/{$video->id}");
        $this->assertEquals($expectedUrl, $data['subject']['url']);
    }

    /**
     * Test activity log resource collection
     */
    public function test_activity_log_resource_collection(): void
    {
        $activityLogs = collect([$this->activityLog]);
        $resource = ActivityLogResource::collection($activityLogs);
        $data = $resource->toArray(request());

        $this->assertIsArray($data);
        $this->assertCount(1, $data);
        $this->assertEquals($this->activityLog->id, $data[0]['id']);
    }

    /**
     * Test activity log resource without user profile
     */
    public function test_activity_log_resource_without_user_profile(): void
    {
        // Delete user profile
        $this->user->profile()->delete();
        $this->activityLog->load('user.profile');

        $resource = new ActivityLogResource($this->activityLog);
        $data = $resource->toArray(request());

        $this->assertArrayHasKey('user', $data);
        $this->assertNull($data['user']['profile']);
    }

    /**
     * Test activity log resource with User subject type
     */
    public function test_activity_log_resource_with_user_subject_type(): void
    {
        $this->activityLog->subject_type = User::class;
        $this->activityLog->subject_id = $this->targetUser->id;
        $this->activityLog->load('subject');

        $resource = new ActivityLogResource($this->activityLog);
        $data = $resource->toArray(request());

        $expectedUrl = url("/users/{$this->targetUser->username}");
        $this->assertEquals($expectedUrl, $data['subject']['url']);
    }
}
