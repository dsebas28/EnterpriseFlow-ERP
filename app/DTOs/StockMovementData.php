<?php

namespace App\DTOs;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;

/**
 * One requested stock change. Quantity is signed: + in, - out.
 */
final readonly class StockMovementData
{
    public function __construct(
        public Product $product,
        public Warehouse $warehouse,
        public int $quantity,
        public StockMovementType $type,
        public ?Model $reference = null,
        public ?int $unitCost = null,
        public ?string $notes = null,
        public ?string $transferId = null,
    ) {}

    /**
     * Key used to lock stock rows in a deterministic order.
     */
    public function lockKey(): string
    {
        return $this->warehouse->id.'|'.$this->product->id;
    }
}
