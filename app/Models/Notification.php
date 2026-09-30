<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Notifications\DatabaseNotification;

/**
 * In-app notification. Not a tenant model: it belongs to a user, and
 * `company_id` (nullable) only says which company it is about.
 *
 * @property string|null $company_id
 * @property array{category?: string, title?: string, body?: string, url?: string|null, level?: string, company_name?: string} $data
 */
class Notification extends DatabaseNotification
{
    use Prunable;

    public const RETENTION_DAYS = 90;

    /**
     * The feed shown while working in a company: its notifications plus
     * personal ones.
     *
     * @param  Builder<static>  $query
     */
    public function scopeVisibleIn(Builder $query, ?string $companyId): void
    {
        $query->where(fn (Builder $q) => $q->whereNull('company_id')
            ->when($companyId !== null, fn (Builder $w) => $w->orWhere('company_id', $companyId)));
    }

    /**
     * Read notifications past retention; unread ones are kept.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()
            ->whereNotNull('read_at')
            ->where('created_at', '<', now()->subDays(self::RETENTION_DAYS));
    }
}
