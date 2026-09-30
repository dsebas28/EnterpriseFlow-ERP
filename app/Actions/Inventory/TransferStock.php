<?php

namespace App\Actions\Inventory;

use App\DTOs\StockMovementData;
use App\Enums\StockMovementType;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryService;
use Illuminate\Support\Str;

/**
 * Moves stock between two warehouses as two linked movements (out + in)
 * recorded atomically: stock is never lost or duplicated in transit.
 */
final class TransferStock
{
    public function __construct(private readonly InventoryService $inventory) {}

    /**
     * @return array{out: StockMovement, in: StockMovement}
     */
    public function handle(Product $product, Warehouse $from, Warehouse $to, int $quantity, ?string $notes = null): array
    {
        if ($from->id === $to->id) {
            throw new BusinessRuleViolation('Choose two different warehouses.');
        }

        if ($quantity <= 0) {
            throw new BusinessRuleViolation('The quantity to transfer must be positive.');
        }

        $transferId = (string) Str::ulid();

        [$out, $in] = $this->inventory->recordMany([
            new StockMovementData($product, $from, -$quantity, StockMovementType::Transfer, notes: $notes, transferId: $transferId),
            new StockMovementData($product, $to, $quantity, StockMovementType::Transfer, notes: $notes, transferId: $transferId),
        ]);

        return ['out' => $out, 'in' => $in];
    }
}
