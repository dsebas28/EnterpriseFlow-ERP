<?php

namespace App\Actions\Roles;

use App\Enums\Permission;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Role;
use App\Services\Authorization\PermissionResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates a custom role or updates an existing one.
 *
 * - Owner is immutable (its permissions are implicit).
 * - System roles keep their name/slug; only their permissions change.
 */
final class SaveRole
{
    public function __construct(private readonly PermissionResolver $permissions) {}

    /**
     * @param  list<Permission>  $permissions
     */
    public function handle(?Role $role, string $name, ?string $description, array $permissions): Role
    {
        if ($role?->isOwner()) {
            throw new BusinessRuleViolation('The Owner role cannot be modified.');
        }

        return DB::transaction(function () use ($role, $name, $description, $permissions): Role {
            $role ??= new Role(['slug' => $this->uniqueSlug($name)]);

            if (! $role->is_system) {
                $role->name = $name;
            }
            $role->description = $description;
            $role->save();

            $role->syncPermissions($permissions);
            $this->permissions->flush();

            return $role;
        });
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'role';
        $slug = $base;

        for ($i = 2; Role::where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }
}
