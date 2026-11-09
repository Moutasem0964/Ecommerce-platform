<?php

namespace App\Modules\Notifications\Http\Controllers;

use App\Modules\Notifications\Http\Resources\NotificationResource;
use App\Modules\Notifications\Services\NotificationService;
use App\Modules\Shared\Http\Controller;
use App\Modules\Shared\Http\Responses\PaginatedResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $notifications)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return PaginatedResponse::make(
            $this->notifications->listFor($request->user()),
            NotificationResource::class,
            ['unread' => $this->notifications->unreadCount($request->user())],
        );
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $notification = $this->notifications->markRead($request->user(), $id);

        return response()->json(['notification' => (new NotificationResource($notification))->resolve()]);
    }
}
