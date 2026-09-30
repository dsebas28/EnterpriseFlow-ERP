<?php

namespace App\Exceptions;

use App\Models\Product;
use App\Models\Warehouse;

class InsufficientStock extends BusinessRuleViolation
{
    public function __construct(
        public readonly Product $product,
        public readonly Warehouse $warehouse,
        public readonly int $available,
        public readonly int $requested,
    ) {
        parent::__construct(sprintf(
            'Not enough stock of %s (%s) in %s: %d available, %d requested.',
            $product->name,
            $product->sku,
            $warehouse->name,
            $available,
            $requested,
        ));
    }
}
