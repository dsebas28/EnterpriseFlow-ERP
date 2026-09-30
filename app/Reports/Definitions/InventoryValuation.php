<?php

namespace App\Reports\Definitions;

use App\Models\Product;
use App\Queries\StockIndexQuery;
use App\Reports\Column;
use App\Reports\ReportFilters;
use App\Reports\Support\BaseReport;

/**
 * Current stock valued at product cost (a snapshot: date filters do not apply).
 */
final class InventoryValuation extends BaseReport
{
    public function __construct(private readonly StockIndexQuery $stock) {}

    public function key(): string
    {
        return 'inventory-valuation';
    }

    public function title(): string
    {
        return 'Inventory valuation';
    }

    public function description(): string
    {
        return 'Units on hand and their value at current cost.';
    }

    public function group(): string
    {
        return 'Inventory';
    }

    public function filters(): array
    {
        return ['warehouse_id', 'category_id'];
    }

    public function columns(): array
    {
        return [
            Column::text('sku', 'SKU'),
            Column::text('product', 'Product'),
            Column::number('on_hand', 'On hand'),
            Column::money('unit_cost', 'Unit cost'),
            Column::money('value', 'Value'),
        ];
    }

    public function rows(ReportFilters $filters): iterable
    {
        return $this->stock
            ->builder(['warehouse_id' => $filters->warehouseId, 'category_id' => $filters->categoryId, 'sort' => 'name'])
            ->without(['category', 'stockLevels'])
            ->lazy()
            ->map(function (Product $product): array {
                $onHand = (int) $product->getAttribute('on_hand');

                return [
                    'sku' => $product->sku,
                    'product' => $product->name,
                    'on_hand' => $onHand,
                    'unit_cost' => $product->cost,
                    'value' => max(0, $onHand) * $product->cost,
                ];
            });
    }

    public function totals(array $rows): array
    {
        $totals = parent::totals($rows);
        unset($totals['unit_cost']);

        return $totals;
    }
}
