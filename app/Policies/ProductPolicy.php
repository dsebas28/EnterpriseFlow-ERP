<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Product;
use App\Models\User;

/**
 * Tenant ownership is already guaranteed by the global scope (a foreign
 * product never resolves); policies decide what the member may do.
 */
class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::ProductsView);
    }

    public function view(User $user, Product $product): bool
    {
        return $user->hasPermission(Permission::ProductsView);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::ProductsCreate);
    }

    public function update(User $user, Product $product): bool
    {
        return $user->hasPermission(Permission::ProductsUpdate);
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->hasPermission(Permission::ProductsDelete);
    }
}
