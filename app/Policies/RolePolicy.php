<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Role;
use App\Models\User;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::RolesManage)
            || $user->hasPermission(Permission::UsersManage);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::RolesManage);
    }

    public function update(User $user, Role $role): bool
    {
        return $user->hasPermission(Permission::RolesManage);
    }

    public function delete(User $user, Role $role): bool
    {
        return $user->hasPermission(Permission::RolesManage);
    }
}
