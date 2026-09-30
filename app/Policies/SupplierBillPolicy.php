<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\SupplierBill;
use App\Models\User;

/**
 * Supplier bills are finance documents: same permissions as invoices.
 */
class SupplierBillPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::InvoicesView);
    }

    public function view(User $user, SupplierBill $bill): bool
    {
        return $user->hasPermission(Permission::InvoicesView);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::InvoicesCreate);
    }

    public function cancel(User $user, SupplierBill $bill): bool
    {
        return $user->hasPermission(Permission::InvoicesCancel);
    }
}
