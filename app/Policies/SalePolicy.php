<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Sale;
use App\Models\User;

/**
 * Permissions per action; the sale status rules live in the actions.
 */
class SalePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::SalesView);
    }

    public function view(User $user, Sale $sale): bool
    {
        return $user->hasPermission(Permission::SalesView);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::SalesCreate);
    }

    public function update(User $user, Sale $sale): bool
    {
        return $user->hasPermission(Permission::SalesCreate);
    }

    public function delete(User $user, Sale $sale): bool
    {
        return $user->hasPermission(Permission::SalesCreate);
    }

    public function confirm(User $user, Sale $sale): bool
    {
        return $user->hasPermission(Permission::SalesConfirm);
    }

    public function cancel(User $user, Sale $sale): bool
    {
        return $user->hasPermission(Permission::SalesCancel);
    }
}
