<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Support\Http\UserAgent;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Lists and revokes a user's browser sessions.
 *
 * Requires the `database` session driver: it is the only built-in driver
 * that indexes sessions by user, which is what makes "see and revoke your
 * other sessions" possible. Redis remains the backend for cache and queues.
 */
final class SessionManager
{
    public function isSupported(): bool
    {
        return config('session.driver') === 'database';
    }

    /**
     * @return Collection<int, array{id: string, ip_address: string|null, platform: string, browser: string, is_current: bool, last_active_at: string}>
     */
    public function forUser(User $user, string $currentSessionId): Collection
    {
        if (! $this->isSupported()) {
            return collect();
        }

        return $this->table()
            ->where('user_id', $user->getKey())
            ->orderByDesc('last_activity')
            ->get(['id', 'ip_address', 'user_agent', 'last_activity'])
            ->map(function (object $session) use ($currentSessionId): array {
                // Raw rows are untyped; normalise each column explicitly.
                $id = (string) $session->id;
                $agent = UserAgent::parse(is_string($session->user_agent) ? $session->user_agent : null);

                return [
                    'id' => $id,
                    'ip_address' => is_string($session->ip_address) ? $session->ip_address : null,
                    'platform' => $agent['platform'],
                    'browser' => $agent['browser'],
                    'is_current' => $id === $currentSessionId,
                    'last_active_at' => Carbon::createFromTimestamp((int) $session->last_activity)->toIso8601String(),
                ];
            });
    }

    /**
     * Revoke one session. Scoped by user so an id belonging to someone else
     * is simply not found.
     */
    public function revoke(User $user, string $sessionId): bool
    {
        return $this->table()
            ->where('user_id', $user->getKey())
            ->where('id', $sessionId)
            ->delete() > 0;
    }

    /**
     * Revoke every session of the user except the current one.
     */
    public function revokeOthers(User $user, string $currentSessionId): int
    {
        if (! $this->isSupported()) {
            return 0;
        }

        return $this->table()
            ->where('user_id', $user->getKey())
            ->where('id', '!=', $currentSessionId)
            ->delete();
    }

    private function table(): Builder
    {
        return DB::connection(config('session.connection'))->table(config('session.table', 'sessions'));
    }
}
