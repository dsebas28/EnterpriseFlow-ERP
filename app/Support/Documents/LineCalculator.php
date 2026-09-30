<?php

namespace App\Support\Documents;

use App\Support\Money\Money;
use InvalidArgumentException;

/**
 * Single source of truth for document line arithmetic (purchases, sales,
 * invoices). Totals are always computed here on the server; totals sent by
 * a client are never trusted.
 *
 * Tax is computed and rounded per line (half-up), then summed. Document
 * totals therefore always equal the sum of their lines.
 */
final class LineCalculator
{
    /**
     * @param  int  $unitPrice  minor units
     * @param  int  $discountRate  basis points (1000 = 10%)
     * @param  int  $taxRate  basis points (1900 = 19%)
     */
    public static function line(int $quantity, int $unitPrice, int $taxRate, int $discountRate = 0, string $currency = 'XXX'): LineAmounts
    {
        if ($quantity <= 0 || $unitPrice < 0 || $taxRate < 0 || $discountRate < 0 || $discountRate > 10_000) {
            throw new InvalidArgumentException('Invalid line values.');
        }

        $gross = (new Money($unitPrice, $currency))->multiply($quantity);
        $discount = $gross->percentage($discountRate);
        $subtotal = $gross->subtract($discount);
        $tax = $subtotal->percentage($taxRate);

        return new LineAmounts(
            gross: $gross->minor,
            discount: $discount->minor,
            subtotal: $subtotal->minor,
            tax: $tax->minor,
            total: $subtotal->add($tax)->minor,
        );
    }

    /**
     * @param  iterable<LineAmounts>  $lines
     * @return array{subtotal: int, discount: int, tax: int, total: int}
     */
    public static function totals(iterable $lines): array
    {
        $totals = ['subtotal' => 0, 'discount' => 0, 'tax' => 0, 'total' => 0];

        foreach ($lines as $line) {
            $totals['subtotal'] += $line->subtotal;
            $totals['discount'] += $line->discount;
            $totals['tax'] += $line->tax;
            $totals['total'] += $line->total;
        }

        return $totals;
    }
}
