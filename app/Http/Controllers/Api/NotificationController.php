<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NotificationController extends Controller
{
    /**
     * @param Request $request
     * @return AnonymousResourceCollection
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $notifications = $request->user()
            ->notifications()
            ->latest('created_at')
            ->paginate(15);

        return NotificationResource::collection($notifications);
    }

    /**
     * @param Request $request
     * @param Notification $notification
     * @return JsonResponse
     */
    public function markAsRead(Request $request, Notification $notification): JsonResponse
    {
        abort_if($notification->user_id !== $request->user()->id, 403, 'Unauthorized.');

        if ($notification->isRead()) {
            return response()->json([
                'message' => 'Notification has already been read.',
                'read_at' => $notification->read_at->toISOString(),
            ], 422);
        }

        $notification->markAsRead();

        return response()->json(['message' => 'Notification marked as read.']);
    }
}
