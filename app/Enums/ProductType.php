<?php

namespace App\Enums;

enum ProductType: string
{
    /** A regular stock-keeping product. */
    case Simple = 'simple';

    /** A template grouping variants (e.g. "T-Shirt"). Never holds stock itself. */
    case Variable = 'variable';

    /** A concrete option of a variable product (e.g. "T-Shirt / M / Red"). */
    case Variant = 'variant';

    public function label(): string
    {
        return match ($this) {
            self::Simple => 'Simple',
            self::Variable => 'With variants',
            self::Variant => 'Variant',
        };
    }

    /**
     * Only concrete items can be bought, sold and counted.
     */
    public function isStockable(): bool
    {
        return $this !== self::Variable;
    }
}
