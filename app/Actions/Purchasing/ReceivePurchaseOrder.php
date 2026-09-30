<?php

namespace App\Actions\Purchasing;

use App\DTOs\StockMovementData;
use App\Enums\DocumentType;
use App\Enums\PurchaseOrderStatus;
use App\Enums\StockMovementType;
use App\Events\PurchaseOrderReceived;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\StockLevel;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Documents\DocumentNumberGenerator;
use App\Services\Inventory\InventoryService;
use App\Support\Documents\WeightedAverageCost;
use Illuminate\Support\Facades\DB;

/**
 * Records goods received against an approved purchase order.
 *
 * One transaction creates the receipt, adds stock through the inventory
 * ledger (type "purchase", with unit cost and a reference to the receipt),
 * updates received quantities, recalculates the weighted average cost and
 * moves the order to partially_received / received. Any failure rolls it
 * all back.
 */
final class ReceivePurchaseOrder
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly DocumentNumberGenerator $numbers,
    ) {}

    /**
     * @param  array<int, int>  $quantities  purchase_order_item_id => quantity received now
     */
    public function handle(PurchaseOrder $order, array $quantities, User $user, ?Warehouse $warehouse = null, ?string $notes = null): PurchaseReceipt
    {
        $quantities = array_filter($quantities, fn (int $quantity) => $quantity > 0);

        if ($quantities === []) {
            throw new BusinessRuleViolation('Enter the quantity received for at least one line.');
        }

        $receipt = DB::transaction(function () use ($order, $quantities, $user, $warehouse, $notes): PurchaseReceipt {
            // Lock the order: two people receiving the same order at once
            // could otherwise both receive the remaining quantity.
            $order = PurchaseOrder::lockForUpdate()->findOrFail($order->id);

            if (! $order->status->canReceive()) {
                throw new BusinessRuleViolation("Purchase order {$order->number} cannot be received ({$order->status->label()}).");
            }

            $warehouse ??= $order->warehouse;
            $items = $order->items()->with('product')->get()->keyBy('id');

            foreach ($quantities as $itemId => $quantity) {
                /** @var PurchaseOrderItem|null $item */
                $item = $items->get($itemId);

                if ($item === null) {
                    throw new BusinessRuleViolation('A received line does not belong to this purchase order.');
                }

                if ($quantity > $item->remainingQuantity()) {
                    throw new BusinessRuleViolation(sprintf(
                        'Cannot receive %d of %s: only %d pending.',
                        $quantity,
                        $item->description,
                        $item->remainingQuantity(),
                    ));
                }
            }

            $receipt = new PurchaseReceipt;
            $receipt->forceFill([
                'number' => $this->numbers->next(DocumentType::PurchaseReceipt),
                'purchase_order_id' => $order->id,
                'warehouse_id' => $warehouse->id,
                'received_by' => $user->id,
                'received_at' => now(),
                'notes' => $notes,
            ])->save();

            $movements = [];
            // Units of each product received earlier in this same receipt:
            // not in the ledger yet, but they must weigh in the average cost.
            $receivedNow = [];

            foreach ($quantities as $itemId => $quantity) {
                /** @var PurchaseOrderItem $item */
                $item = $items->get($itemId);
                /** @var Product $product */
                $product = $item->product;

                $this->updateAverageCost($product, $receivedNow[$product->id] ?? 0, $quantity, $item->unit_cost);
                $receivedNow[$product->id] = ($receivedNow[$product->id] ?? 0) + $quantity;

                $receipt->items()->make()->forceFill([
                    'purchase_order_item_id' => $item->id,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_cost' => $item->unit_cost,
                ])->save();

                $item->forceFill(['received_quantity' => $item->received_quantity + $quantity])->save();

                $movements[] = new StockMovementData(
                    product: $product,
                    warehouse: $warehouse,
                    quantity: $quantity,
                    type: StockMovementType::Purchase,
                    reference: $receipt,
                    unitCost: $item->unit_cost,
                    notes: "Receipt {$receipt->number} for {$order->number}",
                );
            }

            $this->inventory->recordMany($movements);

            $fullyReceived = $items->every(fn (PurchaseOrderItem $item) => $item->remainingQuantity() === 0);
            $order->transitionTo($fullyReceived ? PurchaseOrderStatus::Received : PurchaseOrderStatus::PartiallyReceived);
            if ($fullyReceived) {
                $order->received_at = now();
            }
            $order->save();

            return $receipt;
        });

        PurchaseOrderReceived::dispatch($receipt);

        return $receipt;
    }

    /**
     * Must run before the stock movement is recorded: the average weighs the
     * cost of the units already on hand.
     */
    private function updateAverageCost(Product $product, int $pendingInThisReceipt, int $receivedQuantity, int $receivedCost): void
    {
        $onHand = (int) StockLevel::where('product_id', $product->id)->sum('quantity') + $pendingInThisReceipt;

        $product->forceFill([
            'cost' => WeightedAverageCost::calculate($onHand, $product->cost, $receivedQuantity, $receivedCost),
        ])->save();
    }
}
