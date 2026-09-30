<?php

namespace App\Http\Controllers;

use App\Http\Resources\NotificationResource;
use App\Models\User;
use App\Queries\NotificationFeedQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Notification center. Everything goes through the user's own feed: there
 * is nothing to authorize beyond "it is yours".
 */
class NotificationController extends Controller
{
    public function __construct(private readonly NotificationFeedQuery $feed) {}

    public function index(Request $request): Response
    {
        $unreadOnly = $request->query('filter') === 'unread';

        return Inertia::render('notifications/Index', [
            'notifications' => NotificationResource::collection($this->feed->paginate($this->user($request), $unreadOnly)),
            'filter' => $unreadOnly ? 'unread' : 'all',
            'unread' => $this->feed->unreadCount($this->user($request)),
        ]);
    }

    /**
     * Polled by the header bell.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json(['count' => $this->feed->unreadCount($this->user($request))]);
    }

    /**
     * Mark as read and go to what the notification is about.
     */
    public function open(Request $request, string $id): RedirectResponse
    {
        $notification = $this->feed->findOrFail($this->user($request), $id);
        $notification->markAsRead();

        $url = $notification->data['url'] ?? null;

        // Only follow links to this application (defence in depth: URLs are
        // generated server-side, but stored data is never trusted blindly).
        if (is_string($url) && parse_url($url, PHP_URL_HOST) === $request->getHost()) {
            return redirect()->to($url);
        }

        return redirect()->route('notifications.index');
    }

    public function markRead(Request $request, string $id): RedirectResponse
    {
        $this->feed->findOrFail($this->user($request), $id)->markAsRead();

        return back();
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $this->feed->markAllRead($this->user($request));

        return back()->with('status', 'All notifications marked as read.');
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
