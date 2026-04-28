<?php

namespace Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationCenterController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $limit = max(1, min((int) $request->integer('limit', 10), 50));

            $notifications = $user->notifications()
                ->latest()
                ->limit($limit)
                ->get();

            return response()->json([
                'data' => [
                    'unread_count' => $user->unreadNotifications()->count(),
                    'notifications' => $notifications
                        ->map(fn (DatabaseNotification $notification) => $this->transformNotification($notification))
                        ->values(),
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to fetch notifications: ' . $e->getMessage());
            return response()->json([
                'data' => [
                    'unread_count' => 0,
                    'notifications' => [],
                ],
                'error' => 'Notification service temporarily unavailable.'
            ], 200); // Return 200 to keep UI happy but with empty data
        }
    }

    public function markRead(Request $request): JsonResponse
    {
        $user = $request->user();
        $ids = collect($request->input('notification_ids', []))
            ->filter(fn ($id) => is_string($id) && $id !== '')
            ->values();

        $query = $user->unreadNotifications();

        if ($ids->isNotEmpty()) {
            $query->whereIn('id', $ids->all());
        }

        $updated = $query->update([
            'read_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Notifications marked as read.',
            'data' => [
                'updated' => $updated,
            ],
        ]);
    }

    protected function transformNotification(DatabaseNotification $notification): array
    {
        $data = is_array($notification->data) ? $notification->data : [];

        return [
            'id' => $notification->id,
            'type' => $notification->type,
            'category' => $data['category'] ?? 'system',
            'title' => $data['title'] ?? 'New notification',
            'body' => $data['body'] ?? null,
            'url' => $data['url'] ?? $data['review_url'] ?? $data['action_url'] ?? null,
            'created_at' => optional($notification->created_at)->toIso8601String(),
            'read_at' => optional($notification->read_at)->toIso8601String(),
            'data' => $data,
        ];
    }
}
