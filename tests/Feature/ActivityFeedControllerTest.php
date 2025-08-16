<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\ActivityLog;
use App\Models\Collection;
use App\Models\UserProfile;
use App\Services\ActivityFeedService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ActivityFeedControllerTest extends TestCase
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
     * Test authenticated user can get personalized activity feed
     */
    public function test_authenticated_user_can_get_personalized_activity_feed(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/activity-feed/personalized');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id', 'action', 'properties', 'visibility', 'aggregated_count',
                    'created_at', 'updated_at', 'user', 'target_user', 'subject',
                    'time_ago', 'formatted_action', 'is_aggregated', 'other_users'
                ]
            ],
            'links', 'meta'
        ]);
    }

    /**
     * Test unauthenticated user cannot access personalized feed
     */
    public function test_unauthenticated_user_cannot_access_personalized_feed(): void
    {
        $response = $this->getJson('/api/activity-feed/personalized');

        $response->assertStatus(401);
    }

    /**
     * Test public can access global activity feed
     */
    public function test_public_can_access_global_activity_feed(): void
    {
        $response = $this->getJson('/api/activity-feed/global');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id', 'action', 'properties', 'visibility', 'aggregated_count',
                    'created_at', 'updated_at', 'user', 'target_user', 'subject',
                    'time_ago', 'formatted_action', 'is_aggregated', 'other_users'
                ]
            ],
            'links', 'meta'
        ]);
    }

    /**
     * Test authenticated user can get their own activities
     */
    public function test_authenticated_user_can_get_their_own_activities(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/activity-feed/user');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($this->user->id, $response->json('data.0.user.id'));
    }

    /**
     * Test authenticated user can get targeted activities
     */
    public function test_authenticated_user_can_get_targeted_activities(): void
    {
        Sanctum::actingAs($this->targetUser);

        $response = $this->getJson('/api/activity-feed/targeted');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($this->targetUser->id, $response->json('data.0.target_user.id'));
    }

    /**
     * Test authenticated user can get filtered activities
     */
    public function test_authenticated_user_can_get_filtered_activities(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/activity-feed/filtered?action=collection.liked');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('collection.liked', $response->json('data.0.action'));
    }

    /**
     * Test filtered activities can filter by subject type
     */
    public function test_filtered_activities_can_filter_by_subject_type(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/activity-feed/filtered?subject_type=App\\Models\\Collection');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('App\\Models\\Collection', $response->json('data.0.subject.type'));
    }

    /**
     * Test filtered activities can filter by user
     */
    public function test_filtered_activities_can_filter_by_user(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson("/api/activity-feed/filtered?user_id={$this->user->id}");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($this->user->id, $response->json('data.0.user.id'));
    }

    /**
     * Test user can get activity statistics
     */
    public function test_user_can_get_activity_statistics(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/activity-feed/stats');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'total_activities', 'activities_by_action', 'activities_by_visibility',
            'recent_activity_count', 'most_active_period'
        ]);
    }

    /**
     * Test public can get recent activities for specific user
     */
    public function test_public_can_get_recent_activities_for_specific_user(): void
    {
        $response = $this->getJson("/api/users/{$this->user->username}/activity");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($this->user->id, $response->json('data.0.user.id'));
    }

    /**
     * Test public user activity returns 404 for nonexistent user
     */
    public function test_public_user_activity_returns_404_for_nonexistent_user(): void
    {
        $response = $this->getJson('/api/users/nonexistent/activity');

        $response->assertStatus(404);
    }

    /**
     * Test public can get trending activities
     */
    public function test_public_can_get_trending_activities(): void
    {
        $response = $this->getJson('/api/activity-feed/trending');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id', 'action', 'properties', 'visibility', 'aggregated_count',
                    'created_at', 'updated_at', 'user', 'target_user', 'subject',
                    'time_ago', 'formatted_action', 'is_aggregated', 'other_users'
                ]
            ],
            'links', 'meta'
        ]);
    }

    /**
     * Test trending activities support period filter
     */
    public function test_trending_activities_support_period_filter(): void
    {
        $response = $this->getJson('/api/activity-feed/trending?period=week');

        $response->assertStatus(200);
    }

    /**
     * Test activity feeds support pagination
     */
    public function test_activity_feeds_support_pagination(): void
    {
        Sanctum::actingAs($this->user);

        // Create multiple activities
        for ($i = 0; $i < 20; $i++) {
            ActivityLog::create([
                'user_id' => $this->user->id,
                'action' => 'collection.liked',
                'subject_type' => Collection::class,
                'subject_id' => $this->collection->id,
                'properties' => ['subject_title' => 'Test Collection'],
                'visibility' => 'public',
            ]);
        }

        $response = $this->getJson('/api/activity-feed/user?per_page=10');

        $response->assertStatus(200);
        $this->assertCount(10, $response->json('data'));
        $response->assertJsonStructure(['links', 'meta']);
    }

    /**
     * Test activity feeds validate per_page limits
     */
    public function test_activity_feeds_validate_per_page_limits(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/activity-feed/user?per_page=0');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['per_page']);

        $response = $this->getJson('/api/activity-feed/user?per_page=150');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['per_page']);
    }

    /**
     * Test activity feeds validate feed_type parameter
     */
    public function test_activity_feeds_validate_feed_type_parameter(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/activity-feed/personalized?feed_type=invalid_type');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['feed_type']);
    }

    /**
     * Test activity feeds validate action parameter
     */
    public function test_activity_feeds_validate_action_parameter(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/activity-feed/filtered?action=123');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['action']);
    }

    /**
     * Test activity feeds validate subject_type parameter
     */
    public function test_activity_feeds_validate_subject_type_parameter(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/activity-feed/filtered?subject_type=123');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['subject_type']);
    }

    /**
     * Test activity feeds validate user_id parameter
     */
    public function test_activity_feeds_validate_user_id_parameter(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/activity-feed/filtered?user_id=not_an_integer');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['user_id']);
    }

    /**
     * Test activity feeds validate user_id exists
     */
    public function test_activity_feeds_validate_user_id_exists(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/activity-feed/filtered?user_id=99999');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['user_id']);
    }

    /**
     * Test activities are ordered by created_at desc
     */
    public function test_activities_are_ordered_by_created_at_desc(): void
    {
        Sanctum::actingAs($this->user);

        // Create a newer activity
        $newerActivity = ActivityLog::create([
            'user_id' => $this->user->id,
            'action' => 'video.liked',
            'subject_type' => Collection::class,
            'subject_id' => $this->collection->id,
            'properties' => ['subject_title' => 'Test Collection'],
            'visibility' => 'public',
        ]);

        $response = $this->getJson('/api/activity-feed/user');

        $response->assertStatus(200);
        $activities = $response->json('data');

        $this->assertEquals($newerActivity->id, $activities[0]['id']);
        $this->assertEquals($this->activityLog->id, $activities[1]['id']);
    }

    /**
     * Test public user activity only returns public activities
     */
    public function test_public_user_activity_only_returns_public_activities(): void
    {
        // Create a private activity
        ActivityLog::create([
            'user_id' => $this->user->id,
            'action' => 'collection.created',
            'subject_type' => Collection::class,
            'subject_id' => $this->collection->id,
            'properties' => ['subject_title' => 'Test Collection'],
            'visibility' => 'private',
        ]);

        $response = $this->getJson("/api/users/{$this->user->username}/activity");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data')); // Only the public one
        $this->assertEquals('public', $response->json('data.0.visibility'));
    }

    /**
     * Test activity feed includes relationships when loaded
     */
    public function test_activity_feed_includes_relationships_when_loaded(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/activity-feed/user');

        $response->assertStatus(200);
        $activity = $response->json('data.0');

        $this->assertArrayHasKey('user', $activity);
        $this->assertEquals($this->user->id, $activity['user']['id']);
        $this->assertEquals($this->user->username, $activity['user']['username']);

        $this->assertArrayHasKey('target_user', $activity);
        $this->assertEquals($this->targetUser->id, $activity['target_user']['id']);

        $this->assertArrayHasKey('subject', $activity);
        $this->assertEquals($this->collection->id, $activity['subject']['id']);
        $this->assertEquals('Test Collection', $activity['subject']['title']);
    }

    /**
     * Test activity feed handles empty results gracefully
     */
    public function test_activity_feed_handles_empty_results_gracefully(): void
    {
        Sanctum::actingAs($this->user);

        // Delete all activities
        ActivityLog::truncate();

        $response = $this->getJson('/api/activity-feed/user');

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('data'));
    }

    /**
     * Test filtered activities handle empty results gracefully
     */
    public function test_filtered_activities_handle_empty_results_gracefully(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/activity-feed/filtered?action=nonexistent.action');

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('data'));
    }
}
