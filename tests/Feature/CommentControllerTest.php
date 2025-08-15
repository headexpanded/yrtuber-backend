<?php

namespace Tests\Feature;

use App\Models\Collection;
use App\Models\Comment;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommentControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_comment_on_collection()
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->withDefaults()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/comments', [
            'commentable_type' => 'App\Models\Collection',
            'commentable_id' => $collection->id,
            'content' => 'Great collection!',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Comment created successfully',
            ]);

        $this->assertDatabaseHas('comments', [
            'user_id' => $user->id,
            'commentable_type' => 'App\Models\Collection',
            'commentable_id' => $collection->id,
            'content' => 'Great collection!',
        ]);
    }

    public function test_authenticated_user_can_comment_on_video()
    {
        $user = User::factory()->create();
        $video = Video::factory()->withDefaults()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/comments', [
            'commentable_type' => 'App\Models\Video',
            'commentable_id' => $video->id,
            'content' => 'Amazing video!',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Comment created successfully',
            ]);

        $this->assertDatabaseHas('comments', [
            'user_id' => $user->id,
            'commentable_type' => 'App\Models\Video',
            'commentable_id' => $video->id,
            'content' => 'Amazing video!',
        ]);
    }

    public function test_comment_validation_requires_commentable_type()
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->withDefaults()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/comments', [
            'commentable_id' => $collection->id,
            'content' => 'Great collection!',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['commentable_type']);
    }

    public function test_comment_validation_requires_commentable_id()
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/comments', [
            'commentable_type' => 'App\Models\Collection',
            'content' => 'Great collection!',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['commentable_id']);
    }

    public function test_comment_validation_requires_content()
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/comments', [
            'commentable_type' => 'App\Models\Collection',
            'commentable_id' => $collection->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['content']);
    }

    public function test_comment_validation_requires_valid_commentable_type()
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->withDefaults()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/comments', [
            'commentable_type' => 'App\Models\InvalidModel',
            'commentable_id' => $collection->id,
            'content' => 'Great collection!',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['commentable_type']);
    }

    public function test_comment_validation_limits_content_length()
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->withDefaults()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/comments', [
            'commentable_type' => 'App\Models\Collection',
            'commentable_id' => $collection->id,
            'content' => str_repeat('a', 1001), // Exceeds 1000 character limit
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['content']);
    }

    public function test_comment_returns_404_for_nonexistent_resource()
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/comments', [
            'commentable_type' => 'App\Models\Collection',
            'commentable_id' => 999,
            'content' => 'Great collection!',
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'message' => 'Resource not found',
            ]);
    }

    public function test_public_can_view_comments_for_collection()
    {
        $collection = Collection::factory()->withDefaults()->create();
        $users = User::factory(3)->create();

        foreach ($users as $user) {
            Comment::factory()->create([
                'user_id' => $user->id,
                'commentable_type' => 'App\Models\Collection',
                'commentable_id' => $collection->id,
                'content' => 'Comment from user ' . $user->id,
            ]);
        }

        $response = $this->getJson('/api/comments?commentable_type=App\Models\Collection&commentable_id=' . $collection->id);

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data'));
    }

    public function test_public_can_view_comments_for_video()
    {
        $video = Video::factory()->withDefaults()->create();
        $users = User::factory(3)->create();

        foreach ($users as $user) {
            Comment::factory()->create([
                'user_id' => $user->id,
                'commentable_type' => 'App\Models\Video',
                'commentable_id' => $video->id,
                'content' => 'Comment from user ' . $user->id,
            ]);
        }

        $response = $this->getJson('/api/comments?commentable_type=App\Models\Video&commentable_id=' . $video->id);

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data'));
    }

    public function test_comments_support_pagination()
    {
        $collection = Collection::factory()->withDefaults()->create();
        $users = User::factory(15)->create();

        foreach ($users as $user) {
            Comment::factory()->create([
                'user_id' => $user->id,
                'commentable_type' => 'App\Models\Collection',
                'commentable_id' => $collection->id,
                'content' => 'Comment from user ' . $user->id,
            ]);
        }

        $response = $this->getJson('/api/comments?commentable_type=App\Models\Collection&commentable_id=' . $collection->id . '&per_page=5');

        $response->assertStatus(200);
        $this->assertCount(5, $response->json('data'));
        $this->assertEquals(15, $response->json('meta.total'));
    }

    public function test_public_can_view_specific_comment()
    {
        $comment = Comment::factory()->create([
            'content' => 'Test comment',
        ]);

        $response = $this->getJson('/api/comments/' . $comment->id);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $comment->id,
                    'content' => 'Test comment',
                ],
            ]);
    }

    public function test_comment_owner_can_update_comment()
    {
        $user = User::factory()->create();
        $comment = Comment::factory()->create([
            'user_id' => $user->id,
            'content' => 'Original comment',
        ]);

        Sanctum::actingAs($user);

        $response = $this->putJson('/api/comments/' . $comment->id, [
            'content' => 'Updated comment',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Comment updated successfully',
                'comment' => [
                    'content' => 'Updated comment',
                ],
            ]);

        $this->assertDatabaseHas('comments', [
            'id' => $comment->id,
            'content' => 'Updated comment',
        ]);
    }

    public function test_user_cannot_update_other_users_comment()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $comment = Comment::factory()->create([
            'user_id' => $otherUser->id,
            'content' => 'Original comment',
        ]);

        Sanctum::actingAs($user);

        $response = $this->putJson('/api/comments/' . $comment->id, [
            'content' => 'Updated comment',
        ]);

        $response->assertStatus(403);
    }

    public function test_comment_owner_can_delete_comment()
    {
        $user = User::factory()->create();
        $comment = Comment::factory()->create([
            'user_id' => $user->id,
        ]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/comments/' . $comment->id);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Comment deleted successfully',
            ]);

        $this->assertDatabaseMissing('comments', [
            'id' => $comment->id,
        ]);
    }

    public function test_user_cannot_delete_other_users_comment()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $comment = Comment::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/comments/' . $comment->id);

        $response->assertStatus(403);
    }

    public function test_comments_include_user_profile_information()
    {
        $comment = Comment::factory()->create();

        $response = $this->getJson('/api/comments?commentable_type=' . $comment->commentable_type . '&commentable_id=' . $comment->commentable_id);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'content',
                        'user' => [
                            'id',
                            'username',
                            'profile',
                        ],
                        'created_at',
                        'updated_at',
                    ],
                ],
            ]);
    }

    public function test_comments_are_ordered_by_created_at_desc()
    {
        $collection = Collection::factory()->withDefaults()->create();
        $user = User::factory()->create();

        $comment1 = Comment::factory()->create([
            'user_id' => $user->id,
            'commentable_type' => 'App\Models\Collection',
            'commentable_id' => $collection->id,
            'content' => 'First comment',
            'created_at' => now()->subDays(2),
        ]);

        $comment2 = Comment::factory()->create([
            'user_id' => $user->id,
            'commentable_type' => 'App\Models\Collection',
            'commentable_id' => $collection->id,
            'content' => 'Second comment',
            'created_at' => now()->subDay(),
        ]);

        $comment3 = Comment::factory()->create([
            'user_id' => $user->id,
            'commentable_type' => 'App\Models\Collection',
            'commentable_id' => $collection->id,
            'content' => 'Third comment',
            'created_at' => now(),
        ]);

        $response = $this->getJson('/api/comments?commentable_type=App\Models\Collection&commentable_id=' . $collection->id);

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertEquals($comment3->id, $data[0]['id']);
        $this->assertEquals($comment2->id, $data[1]['id']);
        $this->assertEquals($comment1->id, $data[2]['id']);
    }
}
