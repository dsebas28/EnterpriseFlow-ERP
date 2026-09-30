<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Customer;
use App\Models\CustomerNote;
use App\Models\User;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::CustomersView);
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->hasPermission(Permission::CustomersView);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::CustomersCreate);
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->hasPermission(Permission::CustomersUpdate);
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $user->hasPermission(Permission::CustomersDelete);
    }

    /**
     * Anyone who can see the customer may leave a note.
     */
    public function addNote(User $user, Customer $customer): bool
    {
        return $user->hasPermission(Permission::CustomersView);
    }

    /**
     * Notes can be removed by their author or by someone who can edit the customer.
     */
    public function deleteNote(User $user, Customer $customer, CustomerNote $note): bool
    {
        return $note->user_id === $user->id || $user->hasPermission(Permission::CustomersUpdate);
    }
}
