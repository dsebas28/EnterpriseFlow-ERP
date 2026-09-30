<?php

namespace App\Reports\Definitions;

use App\Models\Product;
use App\Queries\StockIndexQuery;
use App\Reports\Column;
use App\Reports\ReportFilters;
use App\Reports\Support\BaseReport;

/**
 * Items below their minimum stock (including out of stock), most urgent first.
 */
final class LowStock extends BaseReport
{
    public function __construct(private readonly StockIndexQuery $stock) {}

    public function key(): string
    {
        return 'low-stock';
    }

    public function title(): string
    {
        return 'Low stock';
    }

    public function description(): string
    {
        return 'Products below their minimum stock and how many units are missing.';
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
            Column::number('min_stock', 'Minimum'),
            Column::number('shortfall', 'Shortfall'),
        ];
    }

    public function rows(ReportFilters $filters): iterable
    {
        return $this->stock
            ->builder(['warehouse_id' => $filters->warehouseId, 'category_id' => $filters->categoryId, 'sort' => 'on_hand'])
            ->without(['category', 'stockLevels'])
            ->whereRaw('COALESCE(totals.on_hand, 0) < products.min_stock')
            ->get()
            ->map(function (Product $product): array {
                $onHand = (int) $product->getAttribute('on_hand');

                return [
                    'sku' => $product->sku,
                    'product' => $product->name,
                    'on_hand' => $onHand,
                    'min_stock' => $product->min_stock,
                    'shortfall' => $product->min_stock - $onHand,
                ];
            })
            ->sortByDesc('shortfall')
            ->values()
            ->all();
    }

    public function totals(array $rows): array
    {
        return [];
    }
}
