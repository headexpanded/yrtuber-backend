<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\NotificationRequest;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * @param  NotificationService  $notificationService
     */
    public function __construct(
        private NotificationService $notificationService
    ) {}

    /**
     * Get user's notifications
     */
    public function index(NotificationRequest $request): AnonymousResourceCollection
    {
        $user = Auth::user();
        $perPage = $request->get('per_page', 15);
        $type = $request->get('type');
        $read = $request->get('read');

        $query = $user->receivedNotifications()
            ->with(['actor.profile', 'subject'])
            ->orderBy('created_at', 'desc');

        // Filter by type
        if ($type) {
            $query->where('type', $type);
        }

        // Filter by read status
        if ($read !== null) {
            if ($read) {
                $query->whereNotNull('read_at');
            } else {
                $query->whereNull('read_at');
            }
        }

        $notifications = $query->paginate($perPage);

        return NotificationResource::collection($notifications);
    }

    /**
     * Get unread notification count
     */
    public function unreadCount(): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        /** @var User $user */
        $count = $this->notificationService->getUnreadCount($user);

        return response()->json([
            'unread_count' => $count,
        ]);
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(Notification $notification): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        /** @var User $user */

        // Ensure user owns this notification
        if ($notification->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $this->notificationService->markAsRead($notification);

        return response()->json([
            'message' => 'Notification marked as read',
            'notification' => new NotificationResource($notification->load(['actor.profile', 'subject'])),
        ]);
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        /** @var User $user */
        $count = $this->notificationService->markAllAsRead($user);

        return response()->json([
            'message' => "{$count} notifications marked as read",
            'marked_count' => $count,
        ]);
    }

    /**
     * Delete a notification
     */
    public function destroy(Notification $notification): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        /** @var User $user */

        // Ensure user owns this notification
        if ($notification->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $notification->delete();

        return response()->json([
            'message' => 'Notification deleted successfully',
        ]);
    }

    /**
     * Get notification statistics
     */
    public function stats(): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        /** @var User $user */

        $stats = [
            'total' => $user->receivedNotifications()->count(),
            'unread' => $user->receivedNotifications()->whereNull('read_at')->count(),
            'read' => $user->receivedNotifications()->whereNotNull('read_at')->count(),
            'by_type' => $user->receivedNotifications()
                ->selectRaw('type, count(*) as count')
                ->groupBy('type')
                ->pluck('count', 'type')
                ->toArray(),
        ];

        return response()->json($stats);
    }

    /**
     * Get sent notifications (notifications where user is the actor)
     */
    public function sent(Request $request): AnonymousResourceCollection|JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        /** @var User $user */
        $perPage = $request->get('per_page', 15);

        $notifications = $user->sentNotifications()
            ->with(['user.profile', 'subject'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        // For sent notifications, we need to transform the data so that the recipient (user) becomes the actor
        $transformedNotifications = $notifications->getCollection()->map(function ($notification) {
            // Clone the notification and swap the relationships
            $notification->setRelation('actor', $notification->user);
            $notification->setRelation('user', null);
            return $notification;
        });

        // Create a new paginator with transformed results
        $transformedPaginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $transformedNotifications,
            $notifications->total(),
            $notifications->perPage(),
            $notifications->currentPage(),
            ['path' => request()->url()]
        );

        return NotificationResource::collection($transformedPaginator);
    }
}
