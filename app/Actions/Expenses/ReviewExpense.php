<?php

namespace App\Actions\Expenses;

use App\Enums\ExpenseStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Approves or rejects a pending expense. Segregation of duties: the person
 * who recorded an expense can never approve it themselves.
 */
final class ReviewExpense
{
    public function approve(Expense $expense, User $reviewer): Expense
    {
        return $this->review($expense, $reviewer, ExpenseStatus::Approved);
    }

    public function reject(Expense $expense, User $reviewer, string $reason): Expense
    {
        return $this->review($expense, $reviewer, ExpenseStatus::Rejected, $reason);
    }

    private function review(Expense $expense, User $reviewer, ExpenseStatus $decision, ?string $reason = null): Expense
    {
        return DB::transaction(function () use ($expense, $reviewer, $decision, $reason): Expense {
            $expense = Expense::lockForUpdate()->findOrFail($expense->id);

            if (! $expense->isPending()) {
                throw new BusinessRuleViolation("Expense {$expense->number} has already been reviewed.");
            }

            if ($expense->created_by === $reviewer->id) {
                throw new BusinessRuleViolation('You cannot review an expense you recorded yourself.');
            }

            $expense->forceFill([
                'status' => $decision,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'rejection_reason' => $reason,
            ])->save();

            return $expense;
        });
    }
}
