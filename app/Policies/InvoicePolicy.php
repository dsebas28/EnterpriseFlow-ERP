<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::InvoicesView);
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->hasPermission(Permission::InvoicesView);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::InvoicesCreate);
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $user->hasPermission(Permission::InvoicesCreate);
    }

    public function cancel(User $user, Invoice $invoice): bool
    {
        return $user->hasPermission(Permission::InvoicesCancel);
    }
}
