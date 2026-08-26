<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AppNotificationController extends Controller
{
    public function index(Request $request)
    {
        $userId = (int) $request->user()->id;

        $items = AppNotification::query()
            ->where('user_id', $userId)
            ->latest('created_at')
            ->limit(100)
            ->get()
            ->map(fn (AppNotification $notification) => $this->payload($notification))
            ->values();

        $unreadCount = AppNotification::query()
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'items' => $items,
            'unread_count' => (int) $unreadCount,
        ]);
    }

    public function show(Request $request, AppNotification $notification)
    {
        abort_unless((int) $notification->user_id === (int) $request->user()->id, 404);

        return response()->json($this->payload($notification));
    }

    public function markRead(Request $request, AppNotification $notification)
    {
        abort_unless((int) $notification->user_id === (int) $request->user()->id, 404);

        if ($notification->read_at === null) {
            $notification->read_at = Carbon::now('UTC');
            $notification->save();
        }

        return response()->json([
            'ok' => true,
            'notification' => $this->payload($notification),
        ]);
    }

    public function markAllRead(Request $request)
    {
        $now = Carbon::now('UTC');

        $updated = AppNotification::query()
            ->where('user_id', (int) $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => $now]);

        return response()->json([
            'ok' => true,
            'updated' => (int) $updated,
            'unread_count' => 0,
        ]);
    }

    public function unreadCount(Request $request)
    {
        $count = AppNotification::query()
            ->where('user_id', (int) $request->user()->id)
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'unread_count' => (int) $count,
        ]);
    }

    private function payload(AppNotification $notification): array
    {
        return [
            'id' => (int) $notification->id,
            'type' => (string) $notification->type,
            'title' => (string) $notification->title,
            'body' => (string) $notification->body,
            'data' => (array) ($notification->data ?? []),
            'read_at' => optional($notification->read_at)?->toIso8601String(),
            'created_at' => optional($notification->created_at)?->toIso8601String(),
            'updated_at' => optional($notification->updated_at)?->toIso8601String(),
        ];
    }
}
