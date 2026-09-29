<?php

namespace App\Models;

use App\Enums\MembershipStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * A user's membership in a company. Roles are attached per membership, so
 * the same person can be Owner in one company and Sales in another.
 *
 * @property int $id
 * @property string $company_id
 * @property int $user_id
 * @property MembershipStatus $status
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
