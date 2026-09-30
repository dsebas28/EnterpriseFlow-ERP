<?php

namespace App\DTOs;

use App\Support\Money\BasisPoints;
use App\Support\Money\Money;

/**
 * Validated purchase order input. Lines carry only what the user chooses
 * (product, quantity, cost, tax); totals are always computed server-side.
 */
final readonly class PurchaseOrderData
{
    /**
     * @param  list<array{product_id: string, quantity: int, unit_cost: int, tax_rate: int}>  $lines
     */
    public function __construct(
        public string $supplierId,
        public string $warehouseId,
        public string $orderDate,
        public ?string $expectedDate,
        public ?string $notes,
        public array $lines,
    ) {}

    /**
     * @param  array<string, mixed>  $data  Output of a FormRequest's validated()
     */
    public static function fromArray(array $data, string $currency): self
    {
        return new self(
            supplierId: $data['supplier_id'],
            warehouseId: $data['warehouse_id'],
            orderDate: $data['order_date'],
            expectedDate: $data['expected_date'] ?? null,
            notes: $data['notes'] ?? null,
            lines: array_values(array_map(fn (array $line) => [
                'product_id' => (string) $line['product_id'],
                'quantity' => (int) $line['quantity'],
                'unit_cost' => Money::fromDecimal((string) $line['unit_cost'], $currency)->minor,
                'tax_rate' => BasisPoints::fromPercent((string) $line['tax_rate']),
            ], $data['lines'])),
        );
    }
}
