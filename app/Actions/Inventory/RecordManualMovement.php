<?php

namespace App\Actions\Inventory;

use App\DTOs\StockMovementData;
use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryService;

/**
 * Manual entry or exit outside purchasing/sales (samples, breakage,
 * internal consumption, opening balances).
 */
final class RecordManualMovement
{
    public function __construct(private readonly InventoryService $inventory) {}

    public function handle(Product $product, Warehouse $warehouse, StockMovementType $type, int $quantity, string $notes, ?int $unitCost = null): StockMovement
    {
        return $this->inventory->record(new StockMovementData(
            product: $product,
            warehouse: $warehouse,
            quantity: $type === StockMovementType::ManualOut ? -abs($quantity) : abs($quantity),
            type: $type,
            unitCost: $unitCost,
            notes: $notes,
        ));
    }
}
