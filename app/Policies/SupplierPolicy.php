<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Supplier;
use App\Models\User;

class SupplierPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::SuppliersView);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::SuppliersCreate);
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return $user->hasPermission(Permission::SuppliersUpdate);
    }

    public function delete(User $user, Supplier $supplier): bool
    {
        return $user->hasPermission(Permission::SuppliersDelete);
    }
}
