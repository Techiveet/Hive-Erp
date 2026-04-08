<?php

namespace Modules\Core\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Subscription\Notifications\DirectTransferReviewSubmitted;

class DirectTransferNotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $limit = max(1, min((int) $request->integer('limit', 10), 50));

        $notifications = $user->notifications()
            ->where('type', DirectTransferReviewSubmitted::class)
            ->latest()
            ->limit($limit)
            ->get();

        $unreadCount = $user->unreadNotifications()
            ->where('type', DirectTransferReviewSubmitted::class)
            ->count();

        return response()->json([
            'data' => [
                'unread_count' => $unreadCount,
                'notifications' => $notifications->map(fn ($notification) => [
                    'id' => $notification->id,
                    'title' => $notification->data['title'] ?? 'Direct transfer submitted',
                    'body' => $notification->data['body'] ?? null,
                    'order_id' => $notification->data['order_id'] ?? null,
                    'review_url' => $notification->data['review_url'] ?? null,
                    'created_at' => optional($notification->created_at)->toIso8601String(),
                    'read_at' => optional($notification->read_at)->toIso8601String(),
                    'data' => $notification->data,
                ])->values(),
            ],
        ]);
    }

    public function markRead(Request $request): JsonResponse
    {
        $user = $request->user();
        $ids = collect($request->input('notification_ids', []))
            ->filter(fn ($id) => is_string($id) && $id !== '')
            ->values();

        $query = $user->unreadNotifications()
            ->where('type', DirectTransferReviewSubmitted::class);

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
}
