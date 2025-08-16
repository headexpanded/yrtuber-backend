<?php

namespace App\Services;

use App\Models\User;
use App\Models\Collection;
use App\Models\Video;
use App\Models\Comment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

class EventService
{
    private NotificationService $notificationService;
    private ActivityFeedService $activityFeedService;

    public function __construct(
        NotificationService $notificationService,
        ActivityFeedService $activityFeedService
    ) {
        $this->notificationService = $notificationService;
        $this->activityFeedService = $activityFeedService;
    }

    /**
     * Handle collection liked event
     */
    public function handleCollectionLiked(User $actor, Collection $collection): void
    {
        try {
            // Create notification for collection owner
            if ($collection->user_id !== $actor->id) {
                $this->notificationService->collectionLiked($collection->user, $actor, $collection);
            }

            // Log activity
            $this->activityFeedService->collectionLiked($actor, $collection);

        } catch (\Exception $e) {
            Log::error('Failed to handle collection liked event', [
                'actor_id' => $actor->id,
                'collection_id' => $collection->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle video liked event
     */
    public function handleVideoLiked(User $actor, Video $video): void
    {
        try {
            // Get video owner through primary collection
            $owner = $video->owner;

            if ($owner && $owner->id !== $actor->id) {
                $this->notificationService->videoLiked($owner, $actor, $video);
            }

            // Log activity
            $this->activityFeedService->videoLiked($actor, $video);

        } catch (\Exception $e) {
            Log::error('Failed to handle video liked event', [
                'actor_id' => $actor->id,
                'video_id' => $video->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle comment added event
     */
    public function handleCommentAdded(User $actor, Comment $comment, Model $subject): void
    {
        try {
            // Get subject owner
            $owner = $this->getSubjectOwner($subject);

            if ($owner && $owner->id !== $actor->id) {
                $this->notificationService->commentAdded($owner, $actor, $comment, $subject);
            }

            // Log activity
            $this->activityFeedService->commentAdded($actor, $comment, $subject);

        } catch (\Exception $e) {
            Log::error('Failed to handle comment added event', [
                'actor_id' => $actor->id,
                'comment_id' => $comment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle user followed event
     */
    public function handleUserFollowed(User $follower, User $followed): void
    {
        try {
            // Create notification for followed user
            $this->notificationService->userFollowed($followed, $follower);

            // Log activity
            $this->activityFeedService->userFollowed($follower, $followed);

        } catch (\Exception $e) {
            Log::error('Failed to handle user followed event', [
                'follower_id' => $follower->id,
                'followed_id' => $followed->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle collection created event
     */
    public function handleCollectionCreated(User $user, Collection $collection): void
    {
        try {
            // Log activity
            $this->activityFeedService->collectionCreated($user, $collection);

        } catch (\Exception $e) {
            Log::error('Failed to handle collection created event', [
                'user_id' => $user->id,
                'collection_id' => $collection->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle video added to collection event
     */
    public function handleVideoAddedToCollection(User $user, Video $video, Collection $collection): void
    {
        try {
            // Log activity
            $this->activityFeedService->videoAdded($user, $video, $collection);

        } catch (\Exception $e) {
            Log::error('Failed to handle video added to collection event', [
                'user_id' => $user->id,
                'video_id' => $video->id,
                'collection_id' => $collection->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle collection shared event
     */
    public function handleCollectionShared(User $user, Collection $collection, string $platform): void
    {
        try {
            // Create notification for collection owner if different from sharer
            if ($collection->user_id !== $user->id) {
                $this->notificationService->collectionShared($collection->user, $user, $collection);
            }

            // Log activity
            $this->activityFeedService->collectionShared($user, $collection, $platform);

        } catch (\Exception $e) {
            Log::error('Failed to handle collection shared event', [
                'user_id' => $user->id,
                'collection_id' => $collection->id,
                'platform' => $platform,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get subject owner for notifications and activities
     */
    private function getSubjectOwner(Model $subject): ?User
    {
        if (method_exists($subject, 'user')) {
            return $subject->user;
        }

        if (method_exists($subject, 'owner')) {
            return $subject->owner;
        }

        if (property_exists($subject, 'user_id')) {
            return User::find($subject->user_id);
        }

        return null;
    }

    /**
     * Handle bulk events (for performance optimization)
     */
    public function handleBulkEvents(array $events): void
    {
        foreach ($events as $event) {
            try {
                $this->dispatchEvent($event);
            } catch (\Exception $e) {
                Log::warning('Failed to handle bulk event', [
                    'event' => $event,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Dispatch a single event
     */
    private function dispatchEvent(array $event): void
    {
        $type = $event['type'] ?? '';

        switch ($type) {
            case 'collection_liked':
                $this->handleCollectionLiked(
                    User::find($event['actor_id']),
                    Collection::find($event['collection_id'])
                );
                break;

            case 'video_liked':
                $this->handleVideoLiked(
                    User::find($event['actor_id']),
                    Video::find($event['video_id'])
                );
                break;

            case 'comment_added':
                $this->handleCommentAdded(
                    User::find($event['actor_id']),
                    Comment::find($event['comment_id']),
                    $this->getSubjectFromEvent($event)
                );
                break;

            case 'user_followed':
                $this->handleUserFollowed(
                    User::find($event['follower_id']),
                    User::find($event['followed_id'])
                );
                break;

            case 'collection_created':
                $this->handleCollectionCreated(
                    User::find($event['user_id']),
                    Collection::find($event['collection_id'])
                );
                break;

            case 'video_added':
                $this->handleVideoAddedToCollection(
                    User::find($event['user_id']),
                    Video::find($event['video_id']),
                    Collection::find($event['collection_id'])
                );
                break;

            case 'collection_shared':
                $this->handleCollectionShared(
                    User::find($event['user_id']),
                    Collection::find($event['collection_id']),
                    $event['platform'] ?? 'unknown'
                );
                break;
        }
    }

    /**
     * Get subject from event data
     */
    private function getSubjectFromEvent(array $event): ?Model
    {
        $subjectType = $event['subject_type'] ?? '';
        $subjectId = $event['subject_id'] ?? null;

        if (!$subjectType || !$subjectId) {
            return null;
        }

        try {
            return $subjectType::find($subjectId);
        } catch (\Exception $e) {
            Log::warning('Failed to find subject for event', [
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
