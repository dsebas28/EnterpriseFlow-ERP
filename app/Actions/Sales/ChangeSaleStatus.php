<?php

namespace App\Actions\Sales;

use App\Enums\SaleStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;

/**
 * Transitions that do not move stock: put a draft on hold as pending
 * (e.g. a quote waiting for the customer) or send it back to draft.
 */
final class ChangeSaleStatus
{
    public function markPending(Sale $sale): Sale
    {
        return $this->transition($sale, SaleStatus::Pending, function (Sale $locked): void {
            if (! $locked->items()->exists()) {
                throw new BusinessRuleViolation('Add at least one line first.');
            }
        });
    }

    public function returnToDraft(Sale $sale): Sale
    {
        return $this->transition($sale, SaleStatus::Draft);
    }

    /**
     * @param  (callable(Sale): void)|null  $before
     */
    private function transition(Sale $sale, SaleStatus $target, ?callable $before = null): Sale
    {
        return DB::transaction(function () use ($sale, $target, $before): Sale {
            $locked = Sale::lockForUpdate()->findOrFail($sale->id);
            $locked->transitionTo($target);

            if ($before !== null) {
                $before($locked);
            }

            $locked->save();

            return $locked;
        });
    }
}
