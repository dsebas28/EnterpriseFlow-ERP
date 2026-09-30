<?php

namespace App\Support\Documents;

/**
 * Amounts of one document line, all in minor units.
 */
final readonly class LineAmounts
{
    public function __construct(
        public int $gross,
        public int $discount,
        public int $subtotal,
        public int $tax,
        public int $total,
    ) {}
}
