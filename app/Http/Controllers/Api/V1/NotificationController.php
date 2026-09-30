<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\User;
use App\Queries\NotificationFeedQuery;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(private readonly NotificationFeedQuery $feed) {}

    /**
     * Notifications of the active company plus personal ones, newest first.
     * `meta.total` with `unread=1` is the unread count.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'unread' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return ApiResponse::success(NotificationResource::collection($this->feed->paginate(
            $this->user($request),
            (bool) ($validated['unread'] ?? false),
            (int) ($validated['per_page'] ?? 20),
        )));
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $notification = $this->feed->findOrFail($this->user($request), $id);
        $notification->markAsRead();

        return ApiResponse::success(NotificationResource::make($notification), 'Notification marked as read.');
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $count = $this->feed->markAllRead($this->user($request));

        return ApiResponse::success(['marked' => $count], 'All notifications marked as read.');
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
