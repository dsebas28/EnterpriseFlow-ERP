<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Expense;
use App\Models\User;

class ExpensePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::ExpensesView);
    }

    public function view(User $user, Expense $expense): bool
    {
        return $user->hasPermission(Permission::ExpensesView);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::ExpensesCreate);
    }

    /**
     * A pending expense can be edited or withdrawn by whoever recorded it,
     * or by someone entitled to approve expenses.
     */
    public function update(User $user, Expense $expense): bool
    {
        return $user->hasPermission(Permission::ExpensesCreate)
            && ($expense->created_by === $user->id || $user->hasPermission(Permission::ExpensesApprove));
    }

    public function delete(User $user, Expense $expense): bool
    {
        return $this->update($user, $expense);
    }

    public function review(User $user, Expense $expense): bool
    {
        return $user->hasPermission(Permission::ExpensesApprove);
    }
}
