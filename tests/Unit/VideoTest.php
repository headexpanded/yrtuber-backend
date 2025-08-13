<?php

namespace Tests\Unit;

use App\Models\Video;
use App\Models\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VideoTest extends TestCase
{
    use RefreshDatabase;

    public function test_video_can_be_created()
    {
        $video = Video::factory()->create([
            'youtube_id' => 'test12345678',
            'title' => 'Test Video',
            'channel_name' => 'Test Channel',
        ]);

        $this->assertDatabaseHas('videos', [
            'youtube_id' => 'test12345678',
            'title' => 'Test Video',
            'channel_name' => 'Test Channel',
        ]);
    }

    public function test_video_has_collections_relationship()
    {
        $video = Video::factory()->create();
        $collection = Collection::factory()->create();

        $video->collections()->attach($collection->id, [
            'position' => 1,
            'curator_notes' => 'Great video!',
        ]);

        $this->assertCount(1, $video->collections);
        $this->assertEquals($collection->id, $video->collections->first()->id);
        $this->assertEquals(1, $video->collections->first()->pivot->position);
        $this->assertEquals('Great video!', $video->collections->first()->pivot->curator_notes);
    }

    public function test_video_fillable_fields()
    {
        $videoData = [
            'youtube_id' => 'test12345678',
            'title' => 'Test Video',
            'description' => 'Test description',
            'thumbnail_url' => 'https://example.com/thumb.jpg',
            'channel_name' => 'Test Channel',
            'channel_id' => 'testchannel123456789',
            'duration' => 300,
            'published_at' => now(),
            'view_count' => 1000,
            'like_count' => 50,
            'metadata' => ['category' => 'Education'],
        ];

        $video = Video::create($videoData);

        $this->assertEquals('test12345678', $video->youtube_id);
        $this->assertEquals('Test Video', $video->title);
        $this->assertEquals('Test Channel', $video->channel_name);
        $this->assertEquals(300, $video->duration);
    }

    public function test_video_casts_are_applied()
    {
        $video = Video::factory()->create([
            'published_at' => now(),
            'metadata' => ['category' => 'Education'],
            'view_count' => 1000,
            'like_count' => 50,
            'duration' => 300,
        ]);

        $this->assertInstanceOf(\Carbon\Carbon::class, $video->published_at);
        $this->assertIsArray($video->metadata);
        $this->assertIsInt($video->view_count);
        $this->assertIsInt($video->like_count);
        $this->assertIsInt($video->duration);
    }

    public function test_video_youtube_id_is_unique()
    {
        Video::factory()->create(['youtube_id' => 'test12345678']);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Video::factory()->create(['youtube_id' => 'test12345678']);
    }

    public function test_video_has_default_values()
    {
        $video = Video::factory()->withDefaults()->create();

        $this->assertEquals(0, $video->view_count);
        $this->assertEquals(0, $video->like_count);
    }

    public function test_video_can_be_updated()
    {
        $video = Video::factory()->create(['title' => 'Original Title']);

        $video->update(['title' => 'Updated Title']);

        $this->assertEquals('Updated Title', $video->fresh()->title);
    }

    public function test_video_metadata_can_be_stored_as_json()
    {
        $metadata = [
            'category' => 'Education',
            'tags' => ['tutorial', 'programming'],
            'language' => 'en',
            'quality' => '1080p',
        ];

        $video = Video::factory()->create(['metadata' => $metadata]);

        $this->assertEquals($metadata, $video->metadata);
        $this->assertEquals('Education', $video->metadata['category']);
        $this->assertContains('tutorial', $video->metadata['tags']);
    }

    public function test_video_collections_are_ordered_by_position()
    {
        $video = Video::factory()->create();
        $collection1 = Collection::factory()->create();
        $collection2 = Collection::factory()->create();
        $collection3 = Collection::factory()->create();

        $video->collections()->attach($collection2->id, ['position' => 3]);
        $video->collections()->attach($collection1->id, ['position' => 1]);
        $video->collections()->attach($collection3->id, ['position' => 2]);

        $orderedCollections = $video->fresh()->collections;

        $this->assertEquals($collection1->id, $orderedCollections[0]->id);
        $this->assertEquals($collection3->id, $orderedCollections[1]->id);
        $this->assertEquals($collection2->id, $orderedCollections[2]->id);
    }

    public function test_video_can_be_found_by_youtube_id()
    {
        $video = Video::factory()->create(['youtube_id' => 'test12345678']);

        $foundVideo = Video::where('youtube_id', 'test12345678')->first();

        $this->assertEquals($video->id, $foundVideo->id);
    }

    public function test_video_can_be_found_by_channel_id()
    {
        $video = Video::factory()->create(['channel_id' => 'testchannel123456789']);

        $foundVideos = Video::where('channel_id', 'testchannel123456789')->get();

        $this->assertCount(1, $foundVideos);
        $this->assertEquals($video->id, $foundVideos->first()->id);
    }

    public function test_video_can_be_searched_by_title()
    {
        Video::factory()->create(['title' => 'Programming Tutorial']);
        Video::factory()->create(['title' => 'Cooking Video']);
        Video::factory()->create(['title' => 'Advanced Programming']);

        $programmingVideos = Video::where('title', 'like', '%Programming%')->get();

        $this->assertCount(2, $programmingVideos);
    }

    public function test_video_can_be_filtered_by_duration()
    {
        Video::factory()->create(['duration' => 300]); // 5 minutes
        Video::factory()->create(['duration' => 600]); // 10 minutes
        Video::factory()->create(['duration' => 1200]); // 20 minutes

        $shortVideos = Video::where('duration', '<=', 600)->get();

        $this->assertCount(2, $shortVideos);
    }

    public function test_video_can_be_filtered_by_publication_date()
    {
        $oldVideo = Video::factory()->create(['published_at' => now()->subYear()]);
        $newVideo = Video::factory()->create(['published_at' => now()]);

        $recentVideos = Video::where('published_at', '>=', now()->subMonth())->get();

        $this->assertCount(1, $recentVideos);
        $this->assertEquals($newVideo->id, $recentVideos->first()->id);
    }

    public function test_video_has_polymorphic_relationships()
    {
        $video = Video::factory()->create();

        $this->assertTrue(method_exists($video, 'likes'));
        $this->assertTrue(method_exists($video, 'comments'));
        $this->assertTrue(method_exists($video, 'activityLogs'));
    }
}
