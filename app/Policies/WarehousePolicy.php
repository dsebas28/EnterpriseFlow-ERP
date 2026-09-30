<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;
use App\Models\Warehouse;

class WarehousePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::WarehousesView)
            || $user->hasPermission(Permission::WarehousesManage);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::WarehousesManage);
    }

    public function update(User $user, Warehouse $warehouse): bool
    {
        return $user->hasPermission(Permission::WarehousesManage);
    }

    public function delete(User $user, Warehouse $warehouse): bool
    {
        return $user->hasPermission(Permission::WarehousesManage);
    }
}
