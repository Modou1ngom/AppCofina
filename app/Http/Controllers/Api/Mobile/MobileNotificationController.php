<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Support\NotificationPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class MobileNotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $items = $user->notifications()
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(fn (DatabaseNotification $n) => NotificationPresenter::toMobileArray($n))
            ->values()
            ->all();

        $unreadCount = $user->unreadNotifications()->count();

        return response()->json([
            'data' => $items,
            'notifications' => $items,
            'items' => $items,
            'unread_count' => $unreadCount,
            'unreadCount' => $unreadCount,
        ]);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $notification = $user->notifications()->where('id', $id)->first();
        if ($notification === null) {
            return response()->json(['message' => 'Notification introuvable.'], 404);
        }

        $notification->markAsRead();

        return response()->json([
            'message' => 'Notification marquée comme lue.',
            'data' => NotificationPresenter::toMobileArray($notification),
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $user->unreadNotifications->markAsRead();

        return response()->json([
            'message' => 'Toutes les notifications ont été marquées comme lues.',
            'unread_count' => 0,
            'unreadCount' => 0,
        ]);
    }
}
