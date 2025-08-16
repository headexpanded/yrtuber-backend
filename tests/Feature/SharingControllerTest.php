<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Collection;
use App\Models\Video;
use App\Models\CollectionShare;
use App\Models\UserProfile;
use App\Services\SharingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SharingControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $otherUser;
    private Collection $collection;
    private Video $video;

    protected function setUp(): void
    {
        parent::setUp();

        // Create users with profiles
        $this->user = User::factory()->create();
        $this->otherUser = User::factory()->create();

        UserProfile::factory()->create(['user_id' => $this->user->id]);
        UserProfile::factory()->create(['user_id' => $this->otherUser->id]);

        // Create a collection
        $this->collection = Collection::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Test Collection',
            'is_public' => true,
        ]);

        // Create a video
        $this->video = Video::factory()->create();
        $this->collection->videos()->attach($this->video->id, ['position' => 1]);
    }

    /**
     * Test authenticated user can share a collection
     */
    public function test_authenticated_user_can_share_a_collection(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/collections/{$this->collection->id}/share", [
            'platform' => 'twitter',
            'share_type' => 'public',
        ]);

        $response->assertStatus(201);
        $response->assertJson(['message' => 'Collection shared successfully']);
        $response->assertJsonStructure([
            'message', 'share' => [
                'id', 'platform', 'url', 'share_type', 'shared_at',
                'user', 'collection', 'is_expired', 'is_active'
            ]
        ]);

        $this->assertDatabaseHas('collection_shares', [
            'collection_id' => $this->collection->id,
            'user_id' => $this->user->id,
            'platform' => 'twitter',
            'share_type' => 'public',
        ]);
    }

    /**
     * Test authenticated user can share a video
     */
    public function test_authenticated_user_can_share_a_video(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/videos/{$this->video->id}/share", [
            'platform' => 'facebook',
            'share_type' => 'public',
        ]);

        $response->assertStatus(201);
        $response->assertJson(['message' => 'Video shared successfully']);
        $response->assertJsonStructure([
            'message', 'share' => [
                'id', 'platform', 'url', 'share_type', 'shared_at',
                'user', 'collection', 'is_expired', 'is_active'
            ]
        ]);
    }

    /**
     * Test user cannot share inaccessible collection
     */
    public function test_user_cannot_share_inaccessible_collection(): void
    {
        // Make collection private
        $this->collection->update(['is_public' => false]);

        Sanctum::actingAs($this->otherUser);

        $response = $this->postJson("/api/collections/{$this->collection->id}/share", [
            'platform' => 'twitter',
        ]);

        $response->assertStatus(403);
        $response->assertJson(['message' => 'Collection not accessible']);
    }

    /**
     * Test user cannot share inaccessible video
     */
    public function test_user_cannot_share_inaccessible_video(): void
    {
        // Make collection private
        $this->collection->update(['is_public' => false]);

        Sanctum::actingAs($this->otherUser);

        $response = $this->postJson("/api/videos/{$this->video->id}/share", [
            'platform' => 'facebook',
        ]);

        $response->assertStatus(403);
        $response->assertJson(['message' => 'Video not accessible']);
    }

    /**
     * Test sharing validates required fields
     */
    public function test_sharing_validates_required_fields(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/collections/{$this->collection->id}/share", []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['platform']);
    }

    /**
     * Test sharing validates platform values
     */
    public function test_sharing_validates_platform_values(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/collections/{$this->collection->id}/share", [
            'platform' => 'invalid_platform',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['platform']);
    }

    /**
     * Test sharing validates share type values
     */
    public function test_sharing_validates_share_type_values(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/collections/{$this->collection->id}/share", [
            'platform' => 'twitter',
            'share_type' => 'invalid_type',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['share_type']);
    }

    /**
     * Test sharing validates expiration date
     */
    public function test_sharing_validates_expiration_date(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/collections/{$this->collection->id}/share", [
            'platform' => 'twitter',
            'expires_at' => now()->subDays(1)->toISOString(),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['expires_at']);
    }

    /**
     * Test sharing validates custom URL format
     */
    public function test_sharing_validates_custom_url_format(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/collections/{$this->collection->id}/share", [
            'platform' => 'twitter',
            'custom_url' => 'not_a_url',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['custom_url']);
    }

    /**
     * Test user can get shares for a collection
     */
    public function test_user_can_get_shares_for_a_collection(): void
    {
        Sanctum::actingAs($this->user);

        // Create a share
        CollectionShare::create([
            'collection_id' => $this->collection->id,
            'user_id' => $this->user->id,
            'platform' => 'twitter',
            'url' => 'https://twitter.com/intent/tweet?url=test',
            'share_type' => 'public',
            'shared_at' => now(),
        ]);

        $response = $this->getJson("/api/collections/{$this->collection->id}/shares");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('twitter', $response->json('data.0.platform'));
    }

    /**
     * Test user can get their shares
     */
    public function test_user_can_get_their_shares(): void
    {
        Sanctum::actingAs($this->user);

        // Create a share
        CollectionShare::create([
            'collection_id' => $this->collection->id,
            'user_id' => $this->user->id,
            'platform' => 'twitter',
            'url' => 'https://twitter.com/intent/tweet?url=test',
            'share_type' => 'public',
            'shared_at' => now(),
        ]);

        $response = $this->getJson('/api/shares/user');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('twitter', $response->json('data.0.platform'));
    }

    /**
     * Test collection owner can get share analytics
     */
    public function test_collection_owner_can_get_share_analytics(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson("/api/collections/{$this->collection->id}/shares/analytics");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'total_shares', 'shares_by_platform', 'shares_by_type',
            'total_clicks', 'total_views', 'engagement_rate'
        ]);
    }

    /**
     * Test non-owner cannot get share analytics
     */
    public function test_non_owner_cannot_get_share_analytics(): void
    {
        Sanctum::actingAs($this->otherUser);

        $response = $this->getJson("/api/collections/{$this->collection->id}/shares/analytics");

        $response->assertStatus(403);
        $response->assertJson(['message' => 'Unauthorized']);
    }

    /**
     * Test user can get their share analytics
     */
    public function test_user_can_get_their_share_analytics(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/shares/analytics');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'total_shares', 'shares_by_platform', 'shares_by_type',
            'total_clicks', 'total_views', 'engagement_rate'
        ]);
    }

    /**
     * Test share analytics can be updated
     */
    public function test_share_analytics_can_be_updated(): void
    {
        // Create a share
        $share = CollectionShare::create([
            'collection_id' => $this->collection->id,
            'user_id' => $this->user->id,
            'platform' => 'twitter',
            'url' => 'https://twitter.com/intent/tweet?url=test',
            'share_type' => 'public',
            'shared_at' => now(),
        ]);

        $response = $this->postJson("/api/shares/{$share->id}/analytics", [
            'action' => 'click',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Analytics updated successfully']);
    }

    /**
     * Test user can revoke their share
     */
    public function test_user_can_revoke_their_share(): void
    {
        // Create a share
        $share = CollectionShare::create([
            'collection_id' => $this->collection->id,
            'user_id' => $this->user->id,
            'platform' => 'twitter',
            'url' => 'https://twitter.com/intent/tweet?url=test',
            'share_type' => 'public',
            'shared_at' => now(),
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->deleteJson("/api/shares/{$share->id}");

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Share revoked successfully']);
        $this->assertDatabaseMissing('collection_shares', ['id' => $share->id]);
    }

    /**
     * Test collection owner can revoke any share
     */
    public function test_collection_owner_can_revoke_any_share(): void
    {
        // Create a share by another user
        $share = CollectionShare::create([
            'collection_id' => $this->collection->id,
            'user_id' => $this->otherUser->id,
            'platform' => 'twitter',
            'url' => 'https://twitter.com/intent/tweet?url=test',
            'share_type' => 'public',
            'shared_at' => now(),
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->deleteJson("/api/shares/{$share->id}");

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Share revoked successfully']);
        $this->assertDatabaseMissing('collection_shares', ['id' => $share->id]);
    }

    /**
     * Test user cannot revoke other users share
     */
    public function test_user_cannot_revoke_other_users_share(): void
    {
        // Create a share by another user
        $share = CollectionShare::create([
            'collection_id' => $this->collection->id,
            'user_id' => $this->otherUser->id,
            'platform' => 'twitter',
            'url' => 'https://twitter.com/intent/tweet?url=test',
            'share_type' => 'public',
            'shared_at' => now(),
        ]);

        Sanctum::actingAs($this->otherUser);

        $response = $this->deleteJson("/api/shares/{$share->id}");

        $response->assertStatus(200); // User can revoke their own share
    }

    /**
     * Test user can get embed code
     */
    public function test_user_can_get_embed_code(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson("/api/collections/{$this->collection->id}/embed?platform=iframe");

        $response->assertStatus(200);
        $response->assertJsonStructure(['embed_code', 'platform']);
        $this->assertStringContainsString('<iframe', $response->json('embed_code'));
    }

    /**
     * Test public can get trending shares
     */
    public function test_public_can_get_trending_shares(): void
    {
        $response = $this->getJson('/api/shares/trending');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id', 'platform', 'url', 'share_type', 'shared_at',
                    'user', 'collection', 'is_expired', 'is_active'
                ]
            ],
            'links', 'meta'
        ]);
    }

    /**
     * Test public can get share statistics
     */
    public function test_public_can_get_share_statistics(): void
    {
        $response = $this->getJson('/api/shares/stats');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'total_shares', 'shares_by_platform', 'shares_by_type',
            'total_clicks', 'total_views', 'engagement_rate'
        ]);
    }

    /**
     * Test sharing supports custom URL
     */
    public function test_sharing_supports_custom_url(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/collections/{$this->collection->id}/share", [
            'platform' => 'twitter',
            'custom_url' => 'https://example.com/custom',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('collection_shares', [
            'collection_id' => $this->collection->id,
            'user_id' => $this->user->id,
            'platform' => 'twitter',
            'url' => 'https://example.com/custom',
        ]);
    }

    /**
     * Test sharing supports expiration date
     */
    public function test_sharing_supports_expiration_date(): void
    {
        Sanctum::actingAs($this->user);

        $expiresAt = now()->addDays(7)->toISOString();

        $response = $this->postJson("/api/collections/{$this->collection->id}/share", [
            'platform' => 'twitter',
            'expires_at' => $expiresAt,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('collection_shares', [
            'collection_id' => $this->collection->id,
            'user_id' => $this->user->id,
            'platform' => 'twitter',
        ]);

        $share = CollectionShare::where([
            'collection_id' => $this->collection->id,
            'user_id' => $this->user->id,
            'platform' => 'twitter',
        ])->first();

        $this->assertNotNull($share->expires_at);
        $this->assertEquals(
            now()->parse($expiresAt)->format('Y-m-d H:i:s'),
            $share->expires_at->format('Y-m-d H:i:s')
        );
    }

    /**
     * Test shares support pagination
     */
    public function test_shares_support_pagination(): void
    {
        Sanctum::actingAs($this->user);

        // Create multiple shares
        for ($i = 0; $i < 20; $i++) {
            CollectionShare::create([
                'collection_id' => $this->collection->id,
                'user_id' => $this->user->id,
                'platform' => 'twitter',
                'url' => "https://twitter.com/intent/tweet?url=test{$i}",
                'share_type' => 'public',
                'shared_at' => now(),
            ]);
        }

        $response = $this->getJson('/api/shares/user?per_page=10');

        $response->assertStatus(200);
        $this->assertCount(10, $response->json('data'));
        $response->assertJsonStructure(['links', 'meta']);
    }

    /**
     * Test shares are ordered by created_at desc
     */
    public function test_shares_are_ordered_by_created_at_desc(): void
    {
        Sanctum::actingAs($this->user);

        // Create a newer share
        $newerShare = CollectionShare::create([
            'collection_id' => $this->collection->id,
            'user_id' => $this->user->id,
            'platform' => 'facebook',
            'url' => 'https://facebook.com/sharer/sharer.php?u=test',
            'share_type' => 'public',
            'shared_at' => now(),
        ]);

        // Create an older share
        $olderShare = CollectionShare::create([
            'collection_id' => $this->collection->id,
            'user_id' => $this->user->id,
            'platform' => 'twitter',
            'url' => 'https://twitter.com/intent/tweet?url=test',
            'share_type' => 'public',
            'shared_at' => now()->subHours(1),
        ]);

        $response = $this->getJson('/api/shares/user');

        $response->assertStatus(200);
        $shares = $response->json('data');

        $this->assertEquals($newerShare->id, $shares[0]['id']);
        $this->assertEquals($olderShare->id, $shares[1]['id']);
    }

    /**
     * Test share includes relationships when loaded
     */
    public function test_share_includes_relationships_when_loaded(): void
    {
        Sanctum::actingAs($this->user);

        // Create a share
        CollectionShare::create([
            'collection_id' => $this->collection->id,
            'user_id' => $this->user->id,
            'platform' => 'twitter',
            'url' => 'https://twitter.com/intent/tweet?url=test',
            'share_type' => 'public',
            'shared_at' => now(),
        ]);

        $response = $this->getJson('/api/shares/user');

        $response->assertStatus(200);
        $share = $response->json('data.0');

        $this->assertArrayHasKey('user', $share);
        $this->assertEquals($this->user->id, $share['user']['id']);
        $this->assertEquals($this->user->username, $share['user']['username']);

        $this->assertArrayHasKey('collection', $share);
        $this->assertEquals($this->collection->id, $share['collection']['id']);
        $this->assertEquals('Test Collection', $share['collection']['title']);
    }

    /**
     * Test share handles empty results gracefully
     */
    public function test_share_handles_empty_results_gracefully(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/shares/user');

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('data'));
    }
}
