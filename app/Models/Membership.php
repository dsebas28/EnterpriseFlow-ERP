<?php

namespace App\Models;

use App\Enums\MembershipStatus;
use App\Enums\SystemRole;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * A user's membership in a company. Roles are attached per membership, so
 * the same person can be Owner in one company and Sales in another.
 *
 * @property int $id
 * @property string $company_id
 * @property int $user_id
 * @property MembershipStatus $status
 * @property int|null $invited_by
 * @property Carbon|null $joined_at
 * @property-read User $user
 * @property-read Collection<int, Role> $roles
 */
class Membership extends Pivot
{
    protected $table = 'company_user';

    public $incrementing = true;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
    ];

    protected function casts(): array
    {
        return [
            'status' => MembershipStatus::class,
            'joined_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Memberships are not a BelongsToCompany model (they are what links
     * users to companies), so route-model binding is scoped to the active
     * company explicitly. Fail-closed when no company is active.
     *
     * @param  mixed  $query
     * @param  mixed  $value
     * @param  string|null  $field
     * @return mixed
     */
    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        return parent::resolveRouteBindingQuery($query, $value, $field)
            ->where($this->qualifyColumn('company_id'), app(TenantContext::class)->idOrFail());
    }

    public function hasRole(SystemRole $role): bool
    {
        return $this->roles->contains(fn (Role $r) => $r->is_system && $r->slug === $role->value);
    }

    public function isOwner(): bool
    {
        return $this->hasRole(SystemRole::Owner);
    }

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'membership_role', 'membership_id', 'role_id')
            ->withTimestamps();
    }

    /**
     * Replace the member's roles. The pivot carries company_id so the
     * composite foreign keys can verify both sides share the same company.
     *
     * @param  iterable<Role>  $roles
     */
    public function syncRoles(iterable $roles): void
    {
        $this->roles()->sync(
            collect($roles)->mapWithKeys(fn (Role $role) => [
                $role->id => ['company_id' => $this->company_id],
            ])->all(),
        );

        $this->unsetRelation('roles');
    }

    public function isActive(): bool
    {
        return $this->status === MembershipStatus::Active;
    }
}
