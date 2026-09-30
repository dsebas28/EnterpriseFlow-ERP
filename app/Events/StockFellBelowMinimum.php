<?php

namespace App\Events;

use App\Models\Product;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * The total stock of a product (all warehouses) crossed below its minimum.
 * Fired on the crossing only, not on every movement while already low.
 */
class StockFellBelowMinimum implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly Product $product,
        public readonly int $totalStock,
    ) {}
}
