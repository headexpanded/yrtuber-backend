<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ActivityFeedRequest;
use App\Http\Resources\ActivityLogResource;
use App\Models\User;
use App\Services\ActivityFeedService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;

class ActivityFeedController extends Controller
{
    /**
     * @param  ActivityFeedService  $activityFeedService
     */
    public function __construct(
        private readonly ActivityFeedService $activityFeedService
    ) {}

    /**
     * Get personalized activity feed for authenticated user
     */
    public function personalized(ActivityFeedRequest $request): AnonymousResourceCollection|JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        $perPage = $request->get('per_page', 15);

        /** @var User $user */
        $activities = $this->activityFeedService->getPersonalizedFeed($user, $perPage);

        return ActivityLogResource::collection($activities);
    }

    /**
     * Get global activity feed
     */
    public function global(ActivityFeedRequest $request): AnonymousResourceCollection
    {
        $perPage = $request->get('per_page', 15);

        $activities = $this->activityFeedService->getGlobalFeed($perPage);

        return ActivityLogResource::collection($activities);
    }

    /**
     * Get user's own activities
     */
    public function user(ActivityFeedRequest $request): AnonymousResourceCollection|JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        $perPage = $request->get('per_page', 15);

        $activities = $user->activityLogs()
            ->with(['user.profile', 'subject', 'targetUser.profile'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return ActivityLogResource::collection($activities);
    }

    /**
     * Get activities where user is the target
     */
    public function targeted(ActivityFeedRequest $request): AnonymousResourceCollection|JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        $perPage = $request->get('per_page', 15);

        /** @var User $user */
        $activities = $this->activityFeedService->getTargetedActivities($user, $perPage);

        return ActivityLogResource::collection($activities);
    }

    /**
     * Get filtered activities
     */
    public function filtered(ActivityFeedRequest $request): AnonymousResourceCollection|JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        $perPage = $request->get('per_page', 15);
        $action = $request->get('action');
        $subjectType = $request->get('subject_type');
        $userId = $request->get('user_id');

        // Validate the request
        $validated = $request->validated();

        $query = $user->activityLogs()
            ->with(['user.profile', 'subject', 'targetUser.profile'])
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($action) {
            $query->where('action', $action);
        }

        if ($subjectType) {
            $query->where('subject_type', $subjectType);
        }

        if ($userId) {
            $query->where('user_id', $userId);
        }

        $activities = $query->paginate($perPage);

        return ActivityLogResource::collection($activities);
    }

    /**
     * Get activity statistics for user
     */
    public function stats(): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        $stats = $this->activityFeedService->getActivityStatisticsSummary();

        return response()->json($stats);
    }

    /**
     * Get recent activities for a specific user (public)
     */
    public function userPublic(string $username, ActivityFeedRequest $request): AnonymousResourceCollection
    {
        $perPage = $request->get('per_page', 15);

        // Find user by username
        $user = User::where('username', $username)->firstOrFail();

        // Get public activities for this user
        $activities = $user->activityLogs()
            ->public()
            ->with(['user.profile', 'subject', 'targetUser.profile'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return ActivityLogResource::collection($activities);
    }

    /**
     * Get trending activities
     */
    public function trending(ActivityFeedRequest $request): AnonymousResourceCollection
    {
        $perPage = $request->get('per_page', 15);
        $period = $request->get('period', 'week');

        // Get activities with high engagement (likes, comments, shares)
        $query = \App\Models\ActivityLog::query()
            ->orderBy('created_at', 'desc');

        // Apply period filter
        if ($period !== 'all') {
            $periodMap = [
                'hour' => \Carbon\CarbonInterval::hour(),
                'day' => \Carbon\CarbonInterval::day(),
                'week' => \Carbon\CarbonInterval::week(),
                'month' => \Carbon\CarbonInterval::month(),
                'year' => \Carbon\CarbonInterval::year(),
            ];

            if (isset($periodMap[$period])) {
                $query->where('created_at', '>=', now()->sub($periodMap[$period]));
            }
        }

        $activities = $query->paginate($perPage);

        return ActivityLogResource::collection($activities);
    }
}
