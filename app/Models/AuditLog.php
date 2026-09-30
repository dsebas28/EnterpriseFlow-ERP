<?php

namespace App\Models;

use App\Support\Tenancy\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * One entry of the audit trail. Append-only (model guard + DB triggers).
 *
 * Queries are tenant-scoped (fail-closed) like every tenant model, but
 * rows are written with an explicit company_id by AuditLogger, since some
 * events have no active company.
 *
 * @property int $id
 * @property string|null $company_id
 * @property int|null $user_id
 * @property string $event
 * @property string $auditable_type
 * @property string $auditable_id
 * @property array<string, mixed>|null $old_values
 * @property array<string, mixed>|null $new_values
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $method
 * @property string|null $url
 * @property Carbon $created_at
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['*'];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope);
        static::updating(fn () => throw new LogicException('Audit logs are append-only.'));
        static::deleting(fn () => throw new LogicException('Audit logs are append-only.'));
    }

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
