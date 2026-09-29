<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToCompany;
use App\Support\Tenancy\CompanyScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An invitation for an email address to join a company with a role.
 *
 * @property string $id
 * @property string $company_id
 * @property string $email
 * @property int $role_id
 * @property int|null $invited_by
 * @property Carbon $expires_at
 * @property Carbon|null $accepted_at
 * @property Carbon|null $revoked_at
 */
class Invitation extends Model
{
    use BelongsToCompany, HasUlids;

    public const TTL_DAYS = 7;

    protected $fillable = ['email', 'role_id'];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Find an invitation by its plain token. Used by the invitee, who has
     * no company context yet: the token itself is the capability, so the
     * tenant scope is intentionally not applied here.
     */
    public static function findByToken(string $token): ?self
    {
        return static::withoutGlobalScope(CompanyScope::class)
            ->with(['company', 'role' => fn ($query) => $query->withoutGlobalScope(CompanyScope::class)])
            ->where('token_hash', self::hashToken($token))
            ->first();
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now());
    }

    public function isPending(): bool
    {
        return $this->accepted_at === null
            && $this->revoked_at === null
            && $this->expires_at->isFuture();
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }
}
