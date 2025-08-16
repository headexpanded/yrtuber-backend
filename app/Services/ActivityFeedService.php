<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use App\Models\Collection;
use App\Models\Video;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ActivityFeedService
{
    /**
     * Get personalized activity feed for a user
     */
    public function getPersonalizedFeed(User $user, int $perPage = 15): EloquentCollection
    {
        // Get activities from users the current user follows
        $followingIds = $user->follows->pluck('following_id')->toArray();

        // Get activities from followed users and global activities
        $activities = ActivityLog::query()
            ->where(function ($query) use ($followingIds, $user) {
                $query->whereIn('user_id', $followingIds)
                      ->orWhere('visibility', 'public')
                      ->orWhere('target_user_id', $user->id);
            })
            ->where('user_id', '!=', $user->id) // Exclude own activities
            ->with(['user.profile', 'subject', 'targetUser.profile'])
            ->orderBy('created_at', 'desc')
            ->limit($perPage)
            ->get();

        return $this->aggregateActivities($activities);
    }

    /**
     * Get global activity feed
     */
    public function getGlobalFeed(int $perPage = 15): EloquentCollection
    {
        $activities = ActivityLog::query()
            ->where('visibility', 'public')
            ->with(['user.profile', 'subject', 'targetUser.profile'])
            ->orderBy('created_at', 'desc')
            ->limit($perPage)
            ->get();

        return $this->aggregateActivities($activities);
    }

    /**
     * Get user's own activities
     */
    public function getUserActivities(User $user, int $perPage = 15): EloquentCollection
    {
        $activities = ActivityLog::query()
            ->where('user_id', $user->id)
            ->with(['user.profile', 'subject', 'targetUser.profile'])
            ->orderBy('created_at', 'desc')
            ->limit($perPage)
            ->get();

        return $this->aggregateActivities($activities);
    }

    /**
     * Get activities where user is the target
     */
    public function getTargetedActivities(User $user, int $perPage = 15): EloquentCollection
    {
        $activities = ActivityLog::query()
            ->where('target_user_id', $user->id)
            ->with(['user.profile', 'subject', 'targetUser.profile'])
            ->orderBy('created_at', 'desc')
            ->limit($perPage)
            ->get();

        return $this->aggregateActivities($activities);
    }

    /**
     * Create activity log entry
     */
    public function logActivity(
        User $user,
        string $action,
        Model $subject,
        ?User $targetUser = null,
        array $properties = [],
        string $visibility = 'public'
    ): ActivityLog {
        return ActivityLog::create([
            'user_id' => $user->id,
            'action' => $action,
            'subject_type' => get_class($subject),
            'subject_id' => $subject->id,
            'target_user_id' => $targetUser?->id,
            'properties' => array_merge($properties, [
                'subject_title' => $this->getSubjectTitle($subject),
                'subject_type' => class_basename($subject),
            ]),
            'visibility' => $visibility,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * Aggregate similar activities
     */
    private function aggregateActivities(EloquentCollection $activities): EloquentCollection
    {
        $grouped = $activities->groupBy(function ($activity) {
            return $activity->action . '_' . $activity->subject_type . '_' . $activity->subject_id;
        });

        $aggregated = collect();

        foreach ($grouped as $group) {
            if ($group->count() > 1) {
                // Aggregate similar activities
                $first = $group->first();
                $first->aggregated_count = $group->count();
                $first->properties['other_users'] = $group->slice(1)->pluck('user.username')->toArray();
                $aggregated->push($first);
            } else {
                $aggregated->push($group->first());
            }
        }

        return $aggregated->sortByDesc('created_at')->values();
    }

    /**
     * Get subject title for activity
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
     * Log collection created activity
     */
    public function collectionCreated(User $user, Collection $collection): ActivityLog
    {
        return $this->logActivity(
            $user,
            'collection.created',
            $collection,
            null,
            ['collection_title' => $collection->title]
        );
    }

    /**
     * Log video added activity
     */
    public function videoAdded(User $user, Video $video, Collection $collection): ActivityLog
    {
        return $this->logActivity(
            $user,
            'video.added',
            $video,
            $collection->user,
            [
                'video_title' => $video->title,
                'collection_title' => $collection->title,
            ]
        );
    }

    /**
     * Log collection liked activity
     */
    public function collectionLiked(User $user, Collection $collection): ActivityLog
    {
        return $this->logActivity(
            $user,
            'collection.liked',
            $collection,
            $collection->user
        );
    }

    /**
     * Log video liked activity
     */
    public function videoLiked(User $user, Video $video): ActivityLog
    {
        return $this->logActivity(
            $user,
            'video.liked',
            $video,
            $video->user
        );
    }

    /**
     * Log comment added activity
     */
    public function commentAdded(User $user, Model $comment, Model $subject): ActivityLog
    {
        return $this->logActivity(
            $user,
            'comment.added',
            $comment,
            $this->getSubjectOwner($subject),
            [
                'comment_content' => substr($comment->content ?? '', 0, 100),
                'subject_title' => $this->getSubjectTitle($subject),
            ]
        );
    }

    /**
     * Log user followed activity
     */
    public function userFollowed(User $follower, User $followed): ActivityLog
    {
        return $this->logActivity(
            $follower,
            'user.followed',
            $followed,
            $followed
        );
    }

    /**
     * Log collection shared activity
     */
    public function collectionShared(User $user, Collection $collection, string $platform): ActivityLog
    {
        return $this->logActivity(
            $user,
            'collection.shared',
            $collection,
            $collection->user,
            ['platform' => $platform]
        );
    }

    /**
     * Get subject owner for activity
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
     * Clean up old activities
     */
    public function cleanupOldActivities(int $daysOld = 90): int
    {
        $cutoffDate = now()->subDays($daysOld);

        return ActivityLog::where('created_at', '<', $cutoffDate)
            ->delete();
    }

    /**
     * Get activity statistics for a user
     */
    public function getUserActivityStats(User $user): array
    {
        $stats = ActivityLog::where('user_id', $user->id)
            ->select('action', DB::raw('count(*) as count'))
            ->groupBy('action')
            ->pluck('count', 'action')
            ->toArray();

        return [
            'total_activities' => array_sum($stats),
            'activities_by_type' => $stats,
            'last_activity' => $user->activityLogs()->latest()->first()?->created_at,
        ];
    }
}
