<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\PurchaseOrder;
use App\Models\User;

/**
 * Permissions answer "may this member do X to purchase orders"; whether
 * the order's current status allows X is enforced by the state machine in
 * the actions (so the API gets the same rules).
 */
class PurchaseOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::PurchasesView);
    }

    public function view(User $user, PurchaseOrder $order): bool
    {
        return $user->hasPermission(Permission::PurchasesView);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::PurchasesCreate);
    }

    public function update(User $user, PurchaseOrder $order): bool
    {
        return $user->hasPermission(Permission::PurchasesCreate);
    }

    public function delete(User $user, PurchaseOrder $order): bool
    {
        return $user->hasPermission(Permission::PurchasesCreate);
    }

    public function approve(User $user, PurchaseOrder $order): bool
    {
        return $user->hasPermission(Permission::PurchasesApprove);
    }

    public function receive(User $user, PurchaseOrder $order): bool
    {
        return $user->hasPermission(Permission::PurchasesReceive);
    }

    public function cancel(User $user, PurchaseOrder $order): bool
    {
        return $user->hasPermission(Permission::PurchasesCancel);
    }
}
