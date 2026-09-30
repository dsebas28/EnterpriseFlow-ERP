<?php

namespace App\Actions\Sales;

use App\DTOs\StockMovementData;
use App\Enums\SaleStatus;
use App\Enums\StockMovementType;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Services\Inventory\InventoryService;
use Illuminate\Support\Facades\DB;

/**
 * Cancels a sale. If stock had already left, it comes back through
 * compensating "return" movements that reference the sale: the ledger is
 * never rewritten, so the history shows both the sale and its reversal.
 */
final class CancelSale
{
    public function __construct(private readonly InventoryService $inventory) {}

    public function handle(Sale $sale, User $user, string $reason): Sale
    {
        return DB::transaction(function () use ($sale, $user, $reason): Sale {
            $sale = Sale::lockForUpdate()->findOrFail($sale->id);

            if ($sale->amount_paid > 0) {
                throw new BusinessRuleViolation("Sale {$sale->number} has payments; issue a refund instead of cancelling it.");
            }

            $restoreStock = $sale->status->hasDeductedStock();
            $sale->transitionTo(SaleStatus::Cancelled);

            if ($restoreStock) {
                $this->inventory->recordMany($sale->items()->with('product')->get()->map(fn (SaleItem $item) => new StockMovementData(
                    product: $item->product,
                    warehouse: $sale->warehouse,
                    quantity: $item->quantity,
                    type: StockMovementType::Return,
                    reference: $sale,
                    unitCost: $item->unit_cost,
                    notes: "Cancellation of sale {$sale->number}: {$reason}",
                ))->values()->all());
            }

            $sale->forceFill([
                'cancelled_by' => $user->id,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ])->save();

            return $sale;
        });
    }
}
