<?php

namespace App\Models;

use App\Enums\Permission;
use App\Enums\SystemRole;
use App\Support\Audit\Auditable;
use App\Support\Tenancy\BelongsToCompany;
use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * A company-defined role: a named set of permissions assigned to members.
 *
 * @property int $id
 * @property string $company_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property bool $is_system
 */
class Role extends Model
{
    /** @use HasFactory<RoleFactory> */
    use Auditable, BelongsToCompany, HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_system' => false,
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    /**
     * @return HasMany<RolePermission, $this>
     */
    public function permissionRecords(): HasMany
    {
        return $this->hasMany(RolePermission::class);
    }

    /**
     * @return BelongsToMany<Membership, $this>
     */
    public function memberships(): BelongsToMany
    {
        return $this->belongsToMany(Membership::class, 'membership_role', 'role_id', 'membership_id')
            ->withTimestamps();
    }

    public function isOwner(): bool
    {
        return $this->is_system && $this->slug === SystemRole::Owner->value;
    }

    /**
     * @return Collection<int, Permission>
     */
    public function permissions(): Collection
    {
        $values = $this->isOwner()
            ? collect(Permission::values())
            // Stale names of permissions removed from the enum are ignored.
            : $this->permissionRecords->pluck('permission')->toBase()->intersect(Permission::values());

        return $values->map(fn (string $value): Permission => Permission::from($value))->values();
    }

    /**
     * Replace the role's permissions with exactly the given set.
     *
     * @param  iterable<Permission>  $permissions
     */
    public function syncPermissions(iterable $permissions): void
    {
        $values = collect($permissions)
            ->map(fn (Permission $permission) => $permission->value)
            ->unique()
            ->values();

        $this->permissionRecords()->whereNotIn('permission', $values)->delete();

        $existing = $this->permissionRecords()->pluck('permission');

        RolePermission::insert(
            $values->diff($existing)
                ->map(fn (string $value) => ['role_id' => $this->id, 'permission' => $value])
                ->values()
                ->all(),
        );

        $this->unsetRelation('permissionRecords');
    }
}
