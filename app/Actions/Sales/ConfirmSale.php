<?php

namespace App\Actions\Sales;

use App\DTOs\StockMovementData;
use App\Enums\PartyStatus;
use App\Enums\SaleStatus;
use App\Enums\StockMovementType;
use App\Events\SaleConfirmed;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Services\Inventory\InventoryService;
use Illuminate\Support\Facades\DB;

/**
 * Confirms a sale: the stock leaves the warehouse.
 *
 * Everything happens in one transaction. The sale row is locked (no double
 * confirmation), then all lines are deducted through InventoryService,
 * which locks the stock rows in a fixed order and fails with
 * InsufficientStock if any line cannot be served. On any failure the sale
 * stays in its previous status and no movement exists.
 */
final class ConfirmSale
{
    public function __construct(private readonly InventoryService $inventory) {}

    public function handle(Sale $sale, User $user): Sale
    {
        $confirmed = DB::transaction(function () use ($sale, $user): Sale {
            $sale = Sale::lockForUpdate()->findOrFail($sale->id);
            $sale->transitionTo(SaleStatus::Confirmed);

            $items = $sale->items()->with('product')->get();

            if ($items->isEmpty()) {
                throw new BusinessRuleViolation('Add at least one line before confirming the sale.');
            }

            if ($sale->customer->status !== PartyStatus::Active) {
                throw new BusinessRuleViolation("Customer {$sale->customer->name} is inactive.");
            }

            $this->inventory->recordMany($items->map(fn (SaleItem $item) => new StockMovementData(
                product: $item->product,
                warehouse: $sale->warehouse,
                quantity: -$item->quantity,
                type: StockMovementType::Sale,
                reference: $sale,
                notes: "Sale {$sale->number}",
            ))->values()->all());

            // Snapshot the cost at the moment of sale, for margin reporting.
            foreach ($items as $item) {
                $item->forceFill(['unit_cost' => $item->product->cost])->save();
            }

            $sale->forceFill(['confirmed_by' => $user->id, 'confirmed_at' => now()])->save();

            return $sale;
        });

        SaleConfirmed::dispatch($confirmed);

        return $confirmed;
    }
}
