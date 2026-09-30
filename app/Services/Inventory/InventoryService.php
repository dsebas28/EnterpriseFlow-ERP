<?php

namespace App\Services\Inventory;

use App\DTOs\StockMovementData;
use App\Events\StockFellBelowMinimum;
use App\Events\StockMovementRecorded;
use App\Exceptions\BusinessRuleViolation;
use App\Exceptions\InsufficientStock;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * The only writer of stock. Every change is a ledger movement plus an
 * update of the stock_levels projection, inside one transaction.
 *
 * Concurrency: the (product, warehouse) projection row is locked with
 * SELECT … FOR UPDATE before availability is checked, so two concurrent
 * sales of the last unit are serialised and the second one fails. Rows are
 * always locked in the same (warehouse, product) order to avoid deadlocks
 * between documents that touch the same items in different orders.
 */
final class InventoryService
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function record(StockMovementData $movement): StockMovement
    {
        return $this->recordMany([$movement])[0];
    }

    /**
     * Apply several movements atomically: all succeed or none do.
     *
     * @param  list<StockMovementData>  $movements
     * @return list<StockMovement> in the order given
     */
    public function recordMany(array $movements): array
    {
        foreach ($movements as $movement) {
            $this->validate($movement);
        }

        return DB::transaction(function () use ($movements): array {
            $ordered = $movements;
            uasort($ordered, fn (StockMovementData $a, StockMovementData $b) => strcmp($a->lockKey(), $b->lockKey()));

            $recorded = [];
            foreach ($ordered as $index => $movement) {
                $recorded[$index] = $this->apply($movement);
            }
            ksort($recorded);

            return array_values($recorded);
        });
    }

    private function validate(StockMovementData $movement): void
    {
        if (! $movement->type->allows($movement->quantity)) {
            throw new BusinessRuleViolation(sprintf(
                'A %s movement cannot have a quantity of %d.',
                strtolower($movement->type->label()),
                $movement->quantity,
            ));
        }

        if (! $movement->product->type->isStockable()) {
            throw new BusinessRuleViolation("{$movement->product->name} has variants; move stock of a specific variant instead.");
        }

        if (! $movement->warehouse->is_active) {
            throw new BusinessRuleViolation("Warehouse {$movement->warehouse->name} is inactive.");
        }
    }

    private function apply(StockMovementData $data): StockMovement
    {
        $level = $this->lockLevel($data);
        $newQuantity = $level->quantity + $data->quantity;

        if ($newQuantity < 0 && ! $this->allowsNegativeStock()) {
            throw new InsufficientStock($data->product, $data->warehouse, $level->quantity, -$data->quantity);
        }

        $totalBefore = $this->totalStock($data->product->id);

        $level->forceFill(['quantity' => $newQuantity])->save();

        $movement = new StockMovement;
        $movement->forceFill([
            'product_id' => $data->product->id,
            'warehouse_id' => $data->warehouse->id,
            'type' => $data->type,
            'quantity' => $data->quantity,
            'balance_after' => $newQuantity,
            'unit_cost' => $data->unitCost,
            'reference_type' => $data->reference?->getMorphClass(),
            'reference_id' => $data->reference?->getKey(),
            'transfer_id' => $data->transferId,
            'user_id' => Auth::id(),
            'notes' => $data->notes,
            'occurred_at' => now(),
        ])->save();

        StockMovementRecorded::dispatch($movement);

        $totalAfter = $totalBefore + $data->quantity;
        $minimum = $data->product->min_stock;
        if ($minimum > 0 && $totalBefore >= $minimum && $totalAfter < $minimum) {
            StockFellBelowMinimum::dispatch($data->product, $totalAfter);
        }

        return $movement;
    }

    /**
     * Ensure the projection row exists, then lock it. insertOrIgnore avoids a
     * race where two transactions try to create the same row at once.
     */
    private function lockLevel(StockMovementData $data): StockLevel
    {
        DB::table('stock_levels')->insertOrIgnore([
            'company_id' => $this->tenant->idOrFail(),
            'product_id' => $data->product->id,
            'warehouse_id' => $data->warehouse->id,
            'quantity' => 0,
            'updated_at' => now(),
        ]);

        return StockLevel::query()
            ->where('product_id', $data->product->id)
            ->where('warehouse_id', $data->warehouse->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function totalStock(string $productId): int
    {
        return (int) StockLevel::query()->where('product_id', $productId)->sum('quantity');
    }

    private function allowsNegativeStock(): bool
    {
        return (bool) ($this->tenant->companyOrFail()->settings['allow_negative_stock'] ?? false);
    }
}
