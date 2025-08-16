<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Create a notification for a user
     */
    public function createNotification(
        User $user,
        string $type,
        User $actor,
        Model $subject,
        array $data = []
    ): Notification {
        try {
            return Notification::create([
                'user_id' => $user->id,
                'notifiable_type' => User::class,
                'notifiable_id' => $user->id,
                'type' => $type,
                'actor_id' => $actor->id,
                'subject_type' => get_class($subject),
                'subject_id' => $subject->id,
                'data' => array_merge($data, [
                    'actor_name' => $actor->username ?? $actor->email,
                    'subject_title' => $this->getSubjectTitle($subject),
                    'subject_type' => class_basename($subject),
                ]),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create notification', [
                'user_id' => $user->id,
                'type' => $type,
                'actor_id' => $actor->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Create multiple notifications for multiple users
     */
    public function createNotificationsForUsers(
        array $userIds,
        string $type,
        User $actor,
        Model $subject,
        array $data = []
    ): array {
        $notifications = [];

        foreach ($userIds as $userId) {
            try {
                $user = User::find($userId);
                if ($user && $user->id !== $actor->id) { // Don't notify self
                    $notifications[] = $this->createNotification($user, $type, $actor, $subject, $data);
                }
            } catch (\Exception $e) {
                Log::warning('Failed to create notification for user', [
                    'user_id' => $userId,
                    'type' => $type,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $notifications;
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(Notification $notification): bool
    {
        return $notification->update(['read_at' => now()]);
    }

    /**
     * Mark all notifications as read for a user
     */
    public function markAllAsRead(User $user): int
    {
        return $user->receivedNotifications()
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    /**
     * Delete old notifications
     */
    public function deleteOldNotifications(int $daysOld = 30): int
    {
        $cutoffDate = now()->subDays($daysOld);

        return Notification::where('created_at', '<', $cutoffDate)
            ->delete();
    }

    /**
     * Get unread notification count for a user
     */
    public function getUnreadCount(User $user): int
    {
        return $user->receivedNotifications()
            ->whereNull('read_at')
            ->count();
    }

    /**
     * Get subject title for notification
     */
    private function getSubjectTitle(Model $subject): string
    {
        if (method_exists($subject, 'getTitle')) {
            return $subject->getTitle();
        }

        if (method_exists($subject, 'title')) {
            return $subject->title;
        }

        if (method_exists($subject, 'name')) {
            return $subject->name;
        }

        return class_basename($subject);
    }

    /**
     * Create notification for collection liked
     */
    public function collectionLiked(User $collectionOwner, User $actor, Model $collection): Notification
    {
        return $this->createNotification(
            $collectionOwner,
            'collection_liked',
            $actor,
            $collection,
            ['action' => 'liked your collection']
        );
    }

    /**
     * Create notification for video liked
     */
    public function videoLiked(User $videoOwner, User $actor, Model $video): Notification
    {
        return $this->createNotification(
            $videoOwner,
            'video_liked',
            $actor,
            $video,
            ['action' => 'liked your video']
        );
    }

    /**
     * Create notification for comment added
     */
    public function commentAdded(User $contentOwner, User $actor, Model $comment, Model $subject): Notification
    {
        return $this->createNotification(
            $contentOwner,
            'comment_added',
            $actor,
            $comment,
            [
                'action' => 'commented on your ' . strtolower(class_basename($subject)),
                'comment_content' => substr($comment->content ?? '', 0, 100),
            ]
        );
    }

    /**
     * Create notification for user followed
     */
    public function userFollowed(User $followedUser, User $actor): Notification
    {
        return $this->createNotification(
            $followedUser,
            'user_followed',
            $actor,
            $actor,
            ['action' => 'started following you']
        );
    }

    /**
     * Create notification for collection shared
     */
    public function collectionShared(User $collectionOwner, User $actor, Model $collection): Notification
    {
        return $this->createNotification(
            $collectionOwner,
            'collection_shared',
            $actor,
            $collection,
            ['action' => 'shared your collection']
        );
    }
}
