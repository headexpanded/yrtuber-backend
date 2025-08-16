<?php

namespace Tests\Unit;

use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use App\Models\User;
use App\Models\Collection;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationResourceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $actor;
    private Collection $collection;
    private Notification $notification;

    protected function setUp(): void
    {
        parent::setUp();

        // Create users with profiles
        $this->user = User::factory()->create();
        $this->actor = User::factory()->create();

        UserProfile::factory()->create(['user_id' => $this->user->id]);
        UserProfile::factory()->create(['user_id' => $this->actor->id]);

        // Create a collection
        $this->collection = Collection::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Test Collection',
        ]);

        // Create a notification
        $this->notification = Notification::create([
            'user_id' => $this->user->id,
            'notifiable_type' => User::class,
            'notifiable_id' => $this->user->id,
            'type' => 'collection_liked',
            'actor_id' => $this->actor->id,
            'subject_type' => Collection::class,
            'subject_id' => $this->collection->id,
            'data' => [
                'action' => 'liked your collection',
                'actor_name' => $this->actor->username,
                'subject_title' => 'Test Collection',
                'subject_type' => 'Collection',
            ],
        ]);
    }

    /**
     * Test notification resource transforms data correctly
     */
    public function test_notification_resource_transforms_data_correctly(): void
    {
        $resource = new NotificationResource($this->notification);
        $data = $resource->toArray(request());

        $this->assertEquals($this->notification->id, $data['id']);
        $this->assertEquals('collection_liked', $data['type']);
        $this->assertEquals($this->notification->data, $data['data']);
        $this->assertNull($data['read_at']);
        $this->assertNotNull($data['created_at']);
        $this->assertNotNull($data['updated_at']);
    }

    /**
     * Test notification resource includes actor information when loaded
     */
    public function test_notification_resource_includes_actor_information_when_loaded(): void
    {
        $this->notification->load('actor.profile');
        $resource = new NotificationResource($this->notification);
        $data = $resource->toArray(request());

        $this->assertArrayHasKey('actor', $data);
        $this->assertEquals($this->actor->id, $data['actor']['id']);
        $this->assertEquals($this->actor->username, $data['actor']['username']);
        $this->assertEquals($this->actor->email, $data['actor']['email']);
        $this->assertArrayHasKey('profile', $data['actor']);
    }

    /**
     * Test notification resource includes subject information when loaded
     */
    public function test_notification_resource_includes_subject_information_when_loaded(): void
    {
        $this->notification->load('subject');
        $resource = new NotificationResource($this->notification);
        $data = $resource->toArray(request());

        $this->assertArrayHasKey('subject', $data);
        $this->assertEquals($this->collection->id, $data['subject']['id']);
        $this->assertEquals('App\Models\Collection', $data['subject']['type']);
        $this->assertEquals('Test Collection', $data['subject']['title']);
        $this->assertNotNull($data['subject']['url']);
    }

    /**
     * Test notification resource handles null subject gracefully
     */
    public function test_notification_resource_handles_null_subject_gracefully(): void
    {
        $this->notification->subject_type = 'NonExistentModel';
        $this->notification->subject_id = 999;

        $resource = new NotificationResource($this->notification);
        $data = $resource->toArray(request());

        $this->assertNull($data['subject']);
    }

    /**
     * Test notification resource computes is_read correctly
     */
    public function test_notification_resource_computes_is_read_correctly(): void
    {
        $resource = new NotificationResource($this->notification);
        $data = $resource->toArray(request());

        $this->assertFalse($data['is_read']);

        // Mark as read
        $this->notification->update(['read_at' => now()]);
        $resource = new NotificationResource($this->notification);
        $data = $resource->toArray(request());

        $this->assertTrue($data['is_read']);
    }

    /**
     * Test notification resource includes time_ago
     */
    public function test_notification_resource_includes_time_ago(): void
    {
        $resource = new NotificationResource($this->notification);
        $data = $resource->toArray(request());

        $this->assertArrayHasKey('time_ago', $data);
        $this->assertIsString($data['time_ago']);
    }

    /**
     * Test notification resource formats type correctly
     */
    public function test_notification_resource_formats_type_correctly(): void
    {
        $resource = new NotificationResource($this->notification);
        $data = $resource->toArray(request());

        $this->assertEquals('Collection Liked', $data['formatted_type']);
    }

    /**
     * Test notification resource handles unknown types gracefully
     */
    public function test_notification_resource_handles_unknown_types_gracefully(): void
    {
        $this->notification->type = 'unknown_notification_type';

        $resource = new NotificationResource($this->notification);
        $data = $resource->toArray(request());

        $this->assertEquals('Unknown Notification Type', $data['formatted_type']);
    }

    /**
     * Test notification resource generates correct subject URLs
     */
    public function test_notification_resource_generates_correct_subject_urls(): void
    {
        $this->notification->load('subject');
        $resource = new NotificationResource($this->notification);
        $data = $resource->toArray(request());

        $expectedUrl = url("/collections/{$this->collection->slug}");
        $this->assertEquals($expectedUrl, $data['subject']['url']);
    }

    /**
     * Test notification resource handles different subject types
     */
    public function test_notification_resource_handles_different_subject_types(): void
    {
        // Test with Video
        $video = \App\Models\Video::factory()->create();
        $this->notification->subject_type = \App\Models\Video::class;
        $this->notification->subject_id = $video->id;
        $this->notification->load('subject');

        $resource = new NotificationResource($this->notification);
        $data = $resource->toArray(request());

        $expectedUrl = url("/videos/{$video->id}");
        $this->assertEquals($expectedUrl, $data['subject']['url']);
    }

    /**
     * Test notification resource collection
     */
    public function test_notification_resource_collection(): void
    {
        $notifications = collect([$this->notification]);
        $resource = NotificationResource::collection($notifications);
        $data = $resource->toArray(request());

        $this->assertIsArray($data);
        $this->assertCount(1, $data);
        $this->assertEquals($this->notification->id, $data[0]['id']);
    }

    /**
     * Test notification resource with read timestamp
     */
    public function test_notification_resource_with_read_timestamp(): void
    {
        $this->notification->update(['read_at' => now()]);
        $resource = new NotificationResource($this->notification);
        $data = $resource->toArray(request());

        $this->assertNotNull($data['read_at']);
        $this->assertIsString($data['read_at']);
    }

    /**
     * Test notification resource without actor profile
     */
    public function test_notification_resource_without_actor_profile(): void
    {
        // Delete actor profile
        $this->actor->profile()->delete();
        $this->notification->load('actor.profile');

        $resource = new NotificationResource($this->notification);
        $data = $resource->toArray(request());

        $this->assertArrayHasKey('actor', $data);
        $this->assertNull($data['actor']['profile']);
    }
}
