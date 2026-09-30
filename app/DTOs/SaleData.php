<?php

namespace App\DTOs;

use App\Support\Money\BasisPoints;
use App\Support\Money\Money;

/**
 * Validated sale input. Totals are never part of it: they are computed
 * server-side from the lines.
 */
final readonly class SaleData
{
    /**
     * @param  list<array{product_id: string, quantity: int, unit_price: int, discount_rate: int, tax_rate: int}>  $lines
     */
    public function __construct(
        public string $customerId,
        public string $warehouseId,
        public string $saleDate,
        public ?string $notes,
        public array $lines,
    ) {}

    /**
     * @param  array<string, mixed>  $data  Output of a FormRequest's validated()
     */
    public static function fromArray(array $data, string $currency): self
    {
        return new self(
            customerId: $data['customer_id'],
            warehouseId: $data['warehouse_id'],
            saleDate: $data['sale_date'],
            notes: $data['notes'] ?? null,
            lines: array_values(array_map(fn (array $line) => [
                'product_id' => (string) $line['product_id'],
                'quantity' => (int) $line['quantity'],
                'unit_price' => Money::fromDecimal((string) $line['unit_price'], $currency)->minor,
                'discount_rate' => BasisPoints::fromPercent((string) ($line['discount_rate'] ?? '0')),
                'tax_rate' => BasisPoints::fromPercent((string) $line['tax_rate']),
            ], $data['lines'])),
        );
    }
}
