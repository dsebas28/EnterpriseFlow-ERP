<?php

namespace App\Actions\Inventory;

use App\DTOs\StockMovementData;
use App\Enums\StockMovementType;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryService;
use Illuminate\Support\Facades\DB;

/**
 * Physical count: the user states how many units are really on the shelf
 * and the difference is recorded as an adjustment movement.
 */
final class AdjustStock
{
    public function __construct(private readonly InventoryService $inventory) {}

    public function handle(Product $product, Warehouse $warehouse, int $countedQuantity, string $reason): StockMovement
    {
        return DB::transaction(function () use ($product, $warehouse, $countedQuantity, $reason): StockMovement {
            // Read the current quantity under lock so a concurrent movement
            // cannot slip in between reading and adjusting.
            $current = (int) StockLevel::query()
                ->where('product_id', $product->id)
                ->where('warehouse_id', $warehouse->id)
                ->lockForUpdate()
                ->value('quantity');

            $difference = $countedQuantity - $current;

            if ($difference === 0) {
                throw new BusinessRuleViolation('The counted quantity matches the current stock; nothing to adjust.');
            }

            return $this->inventory->record(new StockMovementData(
                product: $product,
                warehouse: $warehouse,
                quantity: $difference,
                type: StockMovementType::Adjustment,
                notes: $reason,
            ));
        });
    }
}
