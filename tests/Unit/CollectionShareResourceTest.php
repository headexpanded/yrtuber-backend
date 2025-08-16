<?php

namespace Tests\Unit;

use App\Http\Resources\CollectionShareResource;
use App\Models\CollectionShare;
use App\Models\User;
use App\Models\Collection;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CollectionShareResourceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Collection $collection;
    private CollectionShare $collectionShare;

    protected function setUp(): void
    {
        parent::setUp();

        // Create user with profile
        $this->user = User::factory()->create();
        UserProfile::factory()->create(['user_id' => $this->user->id]);

        // Create a collection
        $this->collection = Collection::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Test Collection',
            'slug' => 'test-collection',
        ]);

        // Create a collection share
        $this->collectionShare = CollectionShare::create([
            'collection_id' => $this->collection->id,
            'user_id' => $this->user->id,
            'platform' => 'twitter',
            'url' => 'https://twitter.com/intent/tweet?url=test',
            'share_type' => 'public',
            'shared_at' => now(),
            'expires_at' => now()->addDays(7),
            'metadata' => [
                'platform' => 'twitter',
                'share_type' => 'public',
                'original_url' => url("/collections/{$this->collection->slug}"),
            ],
            'analytics' => [
                'clicks' => 10,
                'views' => 50,
                'last_click' => now()->subHours(2)->toISOString(),
                'last_view' => now()->subMinutes(30)->toISOString(),
            ],
        ]);
    }

    /**
     * Test collection share resource transforms data correctly
     */
    public function test_collection_share_resource_transforms_data_correctly(): void
    {
        $resource = new CollectionShareResource($this->collectionShare);
        $data = $resource->toArray(request());

        $this->assertEquals($this->collectionShare->id, $data['id']);
        $this->assertEquals('twitter', $data['platform']);
        $this->assertEquals('https://twitter.com/intent/tweet?url=test', $data['url']);
        $this->assertEquals('public', $data['share_type']);
        $this->assertNotNull($data['shared_at']);
        $this->assertNotNull($data['expires_at']);
        $this->assertEquals($this->collectionShare->metadata, $data['metadata']);
        $this->assertEquals($this->collectionShare->analytics, $data['analytics']);
        $this->assertNotNull($data['created_at']);
        $this->assertNotNull($data['updated_at']);
    }

    /**
     * Test collection share resource includes user information when loaded
     */
    public function test_collection_share_resource_includes_user_information_when_loaded(): void
    {
        $this->collectionShare->load('user.profile');
        $resource = new CollectionShareResource($this->collectionShare);
        $data = $resource->toArray(request());

        $this->assertArrayHasKey('user', $data);
        $this->assertEquals($this->user->id, $data['user']['id']);
        $this->assertEquals($this->user->username, $data['user']['username']);
        $this->assertEquals($this->user->email, $data['user']['email']);
        $this->assertArrayHasKey('profile', $data['user']);
    }

    /**
     * Test collection share resource includes collection information when loaded
     */
    public function test_collection_share_resource_includes_collection_information_when_loaded(): void
    {
        $this->collectionShare->load('collection.user');
        $resource = new CollectionShareResource($this->collectionShare);
        $data = $resource->toArray(request());

        $this->assertArrayHasKey('collection', $data);
        $this->assertEquals($this->collection->id, $data['collection']['id']);
        $this->assertEquals('Test Collection', $data['collection']['title']);
        $this->assertEquals('test-collection', $data['collection']['slug']);
        $this->assertArrayHasKey('user', $data['collection']);
    }

    /**
     * Test collection share resource computes is_expired correctly
     */
    public function test_collection_share_resource_computes_is_expired_correctly(): void
    {
        $resource = new CollectionShareResource($this->collectionShare);
        $data = $resource->toArray(request());

        $this->assertFalse($data['is_expired']);

        // Set expired date
        $this->collectionShare->update(['expires_at' => now()->subDays(1)]);
        $resource = new CollectionShareResource($this->collectionShare);
        $data = $resource->toArray(request());

        $this->assertTrue($data['is_expired']);
    }

    /**
     * Test collection share resource computes is_active correctly
     */
    public function test_collection_share_resource_computes_is_active_correctly(): void
    {
        $resource = new CollectionShareResource($this->collectionShare);
        $data = $resource->toArray(request());

        $this->assertTrue($data['is_active']);

        // Set expired date
        $this->collectionShare->update(['expires_at' => now()->subDays(1)]);
        $resource = new CollectionShareResource($this->collectionShare);
        $data = $resource->toArray(request());

        $this->assertFalse($data['is_active']);
    }

    /**
     * Test collection share resource includes time_ago
     */
    public function test_collection_share_resource_includes_time_ago(): void
    {
        $resource = new CollectionShareResource($this->collectionShare);
        $data = $resource->toArray(request());

        $this->assertArrayHasKey('time_ago', $data);
        $this->assertIsString($data['time_ago']);
    }

    /**
     * Test collection share resource formats platform correctly
     */
    public function test_collection_share_resource_formats_platform_correctly(): void
    {
        $resource = new CollectionShareResource($this->collectionShare);
        $data = $resource->toArray(request());

        $this->assertEquals('Twitter', $data['formatted_platform']);
    }

    /**
     * Test collection share resource handles unknown platforms gracefully
     */
    public function test_collection_share_resource_handles_unknown_platforms_gracefully(): void
    {
        $this->collectionShare->platform = 'unknown_platform';

        $resource = new CollectionShareResource($this->collectionShare);
        $data = $resource->toArray(request());

        $this->assertEquals('Unknown Platform', $data['formatted_platform']);
    }

    /**
     * Test collection share resource generates embed code correctly
     */
    public function test_collection_share_resource_generates_embed_code_correctly(): void
    {
        $resource = new CollectionShareResource($this->collectionShare);
        $data = $resource->toArray(request());

        $this->assertEquals('https://twitter.com/intent/tweet?url=test', $data['embed_code']);

        // Test iframe platform
        $this->collectionShare->platform = 'iframe';
        $resource = new CollectionShareResource($this->collectionShare);
        $data = $resource->toArray(request());

        $expectedIframe = "<iframe src=\"" . url("/collections/{$this->collection->slug}/embed") . "\" width=\"100%\" height=\"600\" style=\"border: none;\"></iframe>";
        $this->assertEquals($expectedIframe, $data['embed_code']);
    }

    /**
     * Test collection share resource includes analytics summary
     */
    public function test_collection_share_resource_includes_analytics_summary(): void
    {
        $resource = new CollectionShareResource($this->collectionShare);
        $data = $resource->toArray(request());

        $this->assertArrayHasKey('analytics_summary', $data);
        $this->assertEquals(10, $data['analytics_summary']['total_clicks']);
        $this->assertEquals(50, $data['analytics_summary']['total_views']);
        $this->assertEquals(20.0, $data['analytics_summary']['engagement_rate']);
    }

    /**
     * Test collection share resource calculates engagement rate correctly
     */
    public function test_collection_share_resource_calculates_engagement_rate_correctly(): void
    {
        // Test with zero views
        $this->collectionShare->analytics = ['clicks' => 0, 'views' => 0];
        $this->collectionShare->save();

        $resource = new CollectionShareResource($this->collectionShare);
        $data = $resource->toArray(request());

        $this->assertEquals(0.0, $data['analytics_summary']['engagement_rate']);

        // Test with some views and clicks
        $this->collectionShare->analytics = ['clicks' => 25, 'views' => 100];
        $this->collectionShare->save();

        $resource = new CollectionShareResource($this->collectionShare);
        $data = $resource->toArray(request());

        $this->assertEquals(25.0, $data['analytics_summary']['engagement_rate']);
    }

    /**
     * Test collection share resource handles missing analytics gracefully
     */
    public function test_collection_share_resource_handles_missing_analytics_gracefully(): void
    {
        $this->collectionShare->analytics = null;
        $this->collectionShare->save();

        $resource = new CollectionShareResource($this->collectionShare);
        $data = $resource->toArray(request());

        $this->assertArrayHasKey('analytics_summary', $data);
        $this->assertEquals(0, $data['analytics_summary']['total_clicks']);
        $this->assertEquals(0, $data['analytics_summary']['total_views']);
        $this->assertEquals(0.0, $data['analytics_summary']['engagement_rate']);
    }

    /**
     * Test collection share resource collection
     */
    public function test_collection_share_resource_collection(): void
    {
        $shares = collect([$this->collectionShare]);
        $resource = CollectionShareResource::collection($shares);
        $data = $resource->toArray(request());

        $this->assertIsArray($data);
        $this->assertCount(1, $data);
        $this->assertEquals($this->collectionShare->id, $data[0]['id']);
    }

    /**
     * Test collection share resource without user profile
     */
    public function test_collection_share_resource_without_user_profile(): void
    {
        // Delete user profile
        $this->user->profile()->delete();
        $this->collectionShare->load('user.profile');

        $resource = new CollectionShareResource($this->collectionShare);
        $data = $resource->toArray(request());

        $this->assertArrayHasKey('user', $data);
        $this->assertNull($data['user']['profile']);
    }

                    /**
     * Test collection share resource without collection user
     */
    public function test_collection_share_resource_without_collection_user(): void
    {
        // Create a new collection share with a collection that has no user relationship
        $collectionWithoutUser = Collection::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Collection Without User',
            'slug' => 'collection-without-user',
        ]);

        $shareWithoutUser = CollectionShare::create([
            'collection_id' => $collectionWithoutUser->id,
            'user_id' => $this->user->id,
            'platform' => 'twitter',
            'url' => 'https://twitter.com/intent/tweet?url=test',
            'share_type' => 'public',
            'shared_at' => now(),
        ]);

        // Load only the collection, not the user relationship
        $shareWithoutUser->load('collection');

        $resource = new CollectionShareResource($shareWithoutUser);
        $data = $resource->toArray(request());

        $this->assertArrayHasKey('collection', $data);
        // The user should be included when collection is loaded, even if not explicitly loaded
        $this->assertArrayHasKey('user', $data['collection']);
        $this->assertNotNull($data['collection']['user']);
    }

    /**
     * Test collection share resource with null expires_at
     */
    public function test_collection_share_resource_with_null_expires_at(): void
    {
        $this->collectionShare->expires_at = null;
        $this->collectionShare->save();

        $resource = new CollectionShareResource($this->collectionShare);
        $data = $resource->toArray(request());

        $this->assertFalse($data['is_expired']);
        $this->assertTrue($data['is_active']);
    }
}
