<?php

namespace App\Modules\Notifications\Http\Controllers;

use App\Modules\Shared\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    /** GET /api/v1/notifications — the logged-in user's notifications, newest first. */
    public function index(Request $request): JsonResponse
    {
        $page = $request->user()->notifications()->paginate(20);

        return response()->json([
            'data' => $page->getCollection()->map(fn (DatabaseNotification $n) => $this->present($n))->values(),
            'meta' => [
                'unread' => $request->user()->unreadNotifications()->count(),
                'current_page' => $page->currentPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }

    /** POST /api/v1/notifications/{id}/read — mark one of MY notifications as read. */
    public function markRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id); // other users' ids → 404
        $notification->markAsRead();

        return response()->json(['notification' => $this->present($notification)]);
    }

    private function present(DatabaseNotification $n): array
    {
        return [
            'id' => $n->id,
            ...$n->data,
            'read' => $n->read_at !== null,
            'created_at' => $n->created_at->toIso8601String(),
        ];
    }
}
