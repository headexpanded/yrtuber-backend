<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Notification;
use App\Models\Collection;
use App\Models\UserProfile;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
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
     * Test authenticated user can get their notifications
     */
    public function test_authenticated_user_can_get_their_notifications(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/notifications');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id', 'type', 'data', 'read_at', 'created_at', 'updated_at',
                    'actor', 'subject', 'is_read', 'time_ago', 'formatted_type'
                ]
            ],
            'links', 'meta'
        ]);
        $this->assertCount(1, $response->json('data'));
    }

    /**
     * Test unauthenticated user cannot access notifications
     */
    public function test_unauthenticated_user_cannot_access_notifications(): void
    {
        $response = $this->getJson('/api/notifications');

        $response->assertStatus(401);
    }

    /**
     * Test notifications can be filtered by type
     */
    public function test_notifications_can_be_filtered_by_type(): void
    {
        Sanctum::actingAs($this->user);

        // Create another notification with different type
        Notification::create([
            'user_id' => $this->user->id,
            'notifiable_type' => User::class,
            'notifiable_id' => $this->user->id,
            'type' => 'video_liked',
            'actor_id' => $this->actor->id,
            'subject_type' => Collection::class,
            'subject_id' => $this->collection->id,
            'data' => ['action' => 'liked your video'],
        ]);

        $response = $this->getJson('/api/notifications?type=collection_liked');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('collection_liked', $response->json('data.0.type'));
    }

    /**
     * Test notifications can be filtered by read status
     */
    public function test_notifications_can_be_filtered_by_read_status(): void
    {
        Sanctum::actingAs($this->user);

        // Mark notification as read
        $this->notification->update(['read_at' => now()]);

        $response = $this->getJson('/api/notifications?read=true');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertTrue($response->json('data.0.is_read'));
    }

    /**
     * Test notifications support pagination
     */
    public function test_notifications_support_pagination(): void
    {
        Sanctum::actingAs($this->user);

        // Create multiple notifications
        for ($i = 0; $i < 20; $i++) {
            Notification::create([
                'user_id' => $this->user->id,
                'notifiable_type' => User::class,
                'notifiable_id' => $this->user->id,
                'type' => 'collection_liked',
                'actor_id' => $this->actor->id,
                'subject_type' => Collection::class,
                'subject_id' => $this->collection->id,
                'data' => ['action' => 'liked your collection'],
            ]);
        }

        $response = $this->getJson('/api/notifications?per_page=10');

        $response->assertStatus(200);
        $this->assertCount(10, $response->json('data'));
        $response->assertJsonStructure(['links', 'meta']);
    }

    /**
     * Test user can get unread notification count
     */
    public function test_user_can_get_unread_notification_count(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/notifications/unread-count');

        $response->assertStatus(200);
        $response->assertJson(['unread_count' => 1]);
    }

    /**
     * Test user can mark notification as read
     */
    public function test_user_can_mark_notification_as_read(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->patchJson("/api/notifications/{$this->notification->id}/read");

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Notification marked as read']);
        $this->assertNotNull($this->notification->fresh()->read_at);
    }

    /**
     * Test user cannot mark other users notification as read
     */
    public function test_user_cannot_mark_other_users_notification_as_read(): void
    {
        $otherUser = User::factory()->create();
        Sanctum::actingAs($otherUser);

        $response = $this->patchJson("/api/notifications/{$this->notification->id}/read");

        $response->assertStatus(403);
        $this->assertNull($this->notification->fresh()->read_at);
    }

    /**
     * Test user can mark all notifications as read
     */
    public function test_user_can_mark_all_notifications_as_read(): void
    {
        Sanctum::actingAs($this->user);

        // Create another notification
        Notification::create([
            'user_id' => $this->user->id,
            'notifiable_type' => User::class,
            'notifiable_id' => $this->user->id,
            'type' => 'video_liked',
            'actor_id' => $this->actor->id,
            'subject_type' => Collection::class,
            'subject_id' => $this->collection->id,
            'data' => ['action' => 'liked your video'],
        ]);

        $response = $this->patchJson('/api/notifications/mark-all-read');

        $response->assertStatus(200);
        $response->assertJson(['marked_count' => 2]);

        $this->assertNotNull($this->notification->fresh()->read_at);
    }

    /**
     * Test user can delete their notification
     */
    public function test_user_can_delete_their_notification(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->deleteJson("/api/notifications/{$this->notification->id}");

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Notification deleted successfully']);
        $this->assertDatabaseMissing('notifications', ['id' => $this->notification->id]);
    }

    /**
     * Test user cannot delete other users notification
     */
    public function test_user_cannot_delete_other_users_notification(): void
    {
        $otherUser = User::factory()->create();
        Sanctum::actingAs($otherUser);

        $response = $this->deleteJson("/api/notifications/{$this->notification->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('notifications', ['id' => $this->notification->id]);
    }

    /**
     * Test user can get notification statistics
     */
    public function test_user_can_get_notification_statistics(): void
    {
        Sanctum::actingAs($this->user);

        // Create notifications with different types
        Notification::create([
            'user_id' => $this->user->id,
            'notifiable_type' => User::class,
            'notifiable_id' => $this->user->id,
            'type' => 'video_liked',
            'actor_id' => $this->actor->id,
            'subject_type' => Collection::class,
            'subject_id' => $this->collection->id,
            'data' => ['action' => 'liked your video'],
        ]);

        $response = $this->getJson('/api/notifications/stats');

        $response->assertStatus(200);
        $response->assertJson([
            'total' => 2,
            'unread' => 2,
            'read' => 0,
            'by_type' => [
                'collection_liked' => 1,
                'video_liked' => 1,
            ]
        ]);
    }

    /**
     * Test user can get sent notifications
     */
    public function test_user_can_get_sent_notifications(): void
    {
        Sanctum::actingAs($this->actor);

        $response = $this->getJson('/api/notifications/sent');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id', 'type', 'data', 'read_at', 'created_at', 'updated_at',
                    'actor', 'subject', 'is_read', 'time_ago', 'formatted_type'
                ]
            ],
            'links', 'meta'
        ]);
        $this->assertCount(1, $response->json('data'));
    }

    /**
     * Test notification includes actor and subject relationships when loaded
     */
    public function test_notification_includes_actor_and_subject_relationships_when_loaded(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/notifications');

        $response->assertStatus(200);
        $notification = $response->json('data.0');

        $this->assertArrayHasKey('actor', $notification);
        $this->assertEquals($this->actor->id, $notification['actor']['id']);
        $this->assertEquals($this->actor->username, $notification['actor']['username']);

        $this->assertArrayHasKey('subject', $notification);
        $this->assertEquals($this->collection->id, $notification['subject']['id']);
        $this->assertEquals('Test Collection', $notification['subject']['title']);
    }

    /**
     * Test notification validation for per_page parameter
     */
    public function test_notification_validation_for_per_page_parameter(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/notifications?per_page=0');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['per_page']);

        $response = $this->getJson('/api/notifications?per_page=150');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['per_page']);
    }

    /**
     * Test notification validation for type parameter
     */
    public function test_notification_validation_for_type_parameter(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/notifications?type=123');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['type']);
    }

    /**
     * Test notification validation for read parameter
     */
    public function test_notification_validation_for_read_parameter(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/notifications?read=not_boolean');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['read']);
    }

    /**
     * Test notifications are ordered by created_at desc
     */
    public function test_notifications_are_ordered_by_created_at_desc(): void
    {
        Sanctum::actingAs($this->user);

        // Create a newer notification
        $newerNotification = Notification::create([
            'user_id' => $this->user->id,
            'notifiable_type' => User::class,
            'notifiable_id' => $this->user->id,
            'type' => 'video_liked',
            'actor_id' => $this->actor->id,
            'subject_type' => Collection::class,
            'subject_id' => $this->collection->id,
            'data' => ['action' => 'liked your video'],
        ]);

        $response = $this->getJson('/api/notifications');

        $response->assertStatus(200);
        $notifications = $response->json('data');

        $this->assertEquals($newerNotification->id, $notifications[0]['id']);
        $this->assertEquals($this->notification->id, $notifications[1]['id']);
    }

    /**
     * Test notification with null read_at is handled correctly
     */
    public function test_notification_with_null_read_at_is_handled_correctly(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/notifications');

        $response->assertStatus(200);
        $notification = $response->json('data.0');

        $this->assertNull($notification['read_at']);
        $this->assertFalse($notification['is_read']);
    }

    /**
     * Test notification with read_at timestamp is handled correctly
     */
    public function test_notification_with_read_at_timestamp_is_handled_correctly(): void
    {
        Sanctum::actingAs($this->user);

        // Mark as read
        $this->notification->update(['read_at' => now()]);

        $response = $this->getJson('/api/notifications');

        $response->assertStatus(200);
        $notification = $response->json('data.0');

        $this->assertNotNull($notification['read_at']);
        $this->assertTrue($notification['is_read']);
    }
}
