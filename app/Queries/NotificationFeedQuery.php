<?php

namespace App\Queries;

use App\Models\Notification;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * The notifications a user sees while working in the active company: that
 * company's plus personal ones. Always rooted at the user's own relation,
 * so an id from someone else's feed can never be read or modified.
 */
final class NotificationFeedQuery
{
    public function __construct(private readonly TenantContext $tenant) {}

    /**
     * @return MorphMany<Notification, User>
     */
    public function for(User $user): MorphMany
    {
        /** @var MorphMany<Notification, User> $query */
        $query = $user->notifications()->visibleIn($this->tenant->id());

        return $query;
    }

    /**
     * @return LengthAwarePaginator<int, Notification>
     */
    public function paginate(User $user, bool $unreadOnly, int $perPage = 20): LengthAwarePaginator
    {
        return $this->for($user)
            ->when($unreadOnly, fn ($query) => $query->whereNull('read_at'))
            ->paginate($perPage)
            ->withQueryString();
    }

    public function unreadCount(User $user): int
    {
        return $this->for($user)->whereNull('read_at')->count();
    }

    public function findOrFail(User $user, string $id): Notification
    {
        return $this->for($user)->whereKey($id)->firstOrFail();
    }

    public function markAllRead(User $user): int
    {
        return $this->for($user)->whereNull('read_at')->update(['read_at' => now()]);
    }
}
