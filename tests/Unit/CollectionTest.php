<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\Collection;
use App\Models\Video;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CollectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_collection_can_be_created()
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->create([
            'user_id' => $user->id,
            'title' => 'Test Collection',
            'slug' => 'test-collection',
        ]);

        $this->assertDatabaseHas('collections', [
            'user_id' => $user->id,
            'title' => 'Test Collection',
            'slug' => 'test-collection',
        ]);
    }

    public function test_collection_has_user_relationship()
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $collection->user);
        $this->assertEquals($user->id, $collection->user->id);
    }

    public function test_collection_has_videos_relationship()
    {
        $collection = Collection::factory()->create();
        $video = Video::factory()->create();

        $collection->videos()->attach($video->id, [
            'position' => 1,
            'curator_notes' => 'Great video!',
        ]);

        $this->assertCount(1, $collection->videos);
        $this->assertEquals($video->id, $collection->videos->first()->id);
        $this->assertEquals(1, $collection->videos->first()->pivot->position);
        $this->assertEquals('Great video!', $collection->videos->first()->pivot->curator_notes);
    }

    public function test_collection_has_tags_relationship()
    {
        $collection = Collection::factory()->create();
        $tag = Tag::factory()->create();

        $collection->tags()->attach($tag->id);

        $this->assertCount(1, $collection->tags);
        $this->assertEquals($tag->id, $collection->tags->first()->id);
    }

    public function test_collection_fillable_fields()
    {
        $collectionData = [
            'user_id' => User::factory()->create()->id,
            'title' => 'Test Collection',
            'slug' => 'test-collection',
            'description' => 'Test description',
            'cover_image' => 'cover.jpg',
            'layout' => 'grid',
            'is_public' => true,
            'is_featured' => false,
            'view_count' => 100,
            'like_count' => 50,
            'video_count' => 10,
        ];

        $collection = Collection::create($collectionData);

        $this->assertEquals('Test Collection', $collection->title);
        $this->assertEquals('test-collection', $collection->slug);
        $this->assertTrue($collection->is_public);
        $this->assertFalse($collection->is_featured);
    }

    public function test_collection_casts_are_applied()
    {
        $collection = Collection::factory()->create([
            'is_public' => true,
            'is_featured' => false,
            'view_count' => 100,
        ]);

        $this->assertTrue($collection->is_public);
        $this->assertFalse($collection->is_featured);
        $this->assertIsInt($collection->view_count);
    }

    public function test_collection_slug_is_generated_automatically()
    {
        $collection = Collection::factory()->create([
            'title' => 'Test Collection Title',
            'slug' => null,
        ]);

        $this->assertEquals('test-collection-title', $collection->slug);
    }

    public function test_collection_slug_is_unique()
    {
        Collection::factory()->create(['slug' => 'test-collection']);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Collection::factory()->create(['slug' => 'test-collection']);
    }

    public function test_collection_has_default_values()
    {
        $collection = Collection::factory()->withDefaults()->create();

        $this->assertEquals('grid', $collection->layout);
        $this->assertTrue($collection->is_public);
        $this->assertFalse($collection->is_featured);
        $this->assertEquals(0, $collection->view_count);
    }

    public function test_collection_can_be_updated()
    {
        $collection = Collection::factory()->create(['title' => 'Original Title']);

        $collection->update(['title' => 'Updated Title']);

        $this->assertEquals('Updated Title', $collection->fresh()->title);
    }

    public function test_collection_deletion_cascades_from_user()
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->create(['user_id' => $user->id]);

        $user->delete();

        $this->assertDatabaseMissing('collections', ['id' => $collection->id]);
    }

    public function test_collection_videos_are_ordered_by_position()
    {
        $collection = Collection::factory()->create();
        $video1 = Video::factory()->create();
        $video2 = Video::factory()->create();
        $video3 = Video::factory()->create();

        $collection->videos()->attach($video2->id, ['position' => 3]);
        $collection->videos()->attach($video1->id, ['position' => 1]);
        $collection->videos()->attach($video3->id, ['position' => 2]);

        $orderedVideos = $collection->videos;

        $this->assertEquals($video1->id, $orderedVideos[0]->id);
        $this->assertEquals($video3->id, $orderedVideos[1]->id);
        $this->assertEquals($video2->id, $orderedVideos[2]->id);
    }

    public function test_collection_public_scope()
    {
        Collection::factory()->create(['is_public' => true]);
        Collection::factory()->create(['is_public' => false]);
        Collection::factory()->create(['is_public' => true]);

        $publicCollections = Collection::where('is_public', true)->get();

        $this->assertCount(2, $publicCollections);
    }

    public function test_collection_featured_scope()
    {
        Collection::factory()->create(['is_featured' => true]);
        Collection::factory()->create(['is_featured' => false]);
        Collection::factory()->create(['is_featured' => true]);

        $featuredCollections = Collection::where('is_featured', true)->get();

        $this->assertCount(2, $featuredCollections);
    }
}
