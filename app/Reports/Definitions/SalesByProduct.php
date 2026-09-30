<?php

namespace App\Reports\Definitions;

use App\Models\SaleItem;
use App\Reports\Column;
use App\Reports\ReportFilters;
use App\Reports\Support\BaseReport;

/**
 * Revenue, cost and margin per product. Cost uses the unit cost captured
 * on each sale line at confirmation, not today's product cost.
 */
final class SalesByProduct extends BaseReport
{
    public function key(): string
    {
        return 'sales-by-product';
    }

    public function title(): string
    {
        return 'Sales by product';
    }

    public function description(): string
    {
        return 'Units, revenue, cost and gross margin per product.';
    }

    public function group(): string
    {
        return 'Sales';
    }

    public function filters(): array
    {
        return ['from', 'to', 'warehouse_id', 'category_id', 'customer_id'];
    }

    public function columns(): array
    {
        return [
            Column::text('sku', 'SKU'),
            Column::text('product', 'Product'),
            Column::number('quantity', 'Units'),
            Column::money('revenue', 'Revenue'),
            Column::money('cost', 'Cost'),
            Column::money('margin', 'Gross margin'),
            Column::percent('margin_pct', 'Margin %'),
        ];
    }

    public function rows(ReportFilters $filters): iterable
    {
        return SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->whereIn('sales.status', $this->completedSaleStatuses())
            ->whereDate('sales.sale_date', '>=', $filters->from)
            ->whereDate('sales.sale_date', '<=', $filters->to)
            ->when($filters->warehouseId, fn ($q, $id) => $q->where('sales.warehouse_id', $id))
            ->when($filters->customerId, fn ($q, $id) => $q->where('sales.customer_id', $id))
            ->when($filters->categoryId, fn ($q, $id) => $q->where('products.category_id', $id))
            ->selectRaw('products.sku, products.name as product, SUM(sale_items.quantity) as quantity, SUM(sale_items.line_subtotal) as revenue, SUM(sale_items.quantity * COALESCE(sale_items.unit_cost, 0)) as cost')
            ->groupBy('products.id', 'products.sku', 'products.name')
            ->orderByDesc('revenue')
            ->toBase()
            ->get()
            ->map(function (object $row): array {
                $revenue = (int) $row->revenue;
                $cost = (int) $row->cost;

                return [
                    'sku' => (string) $row->sku,
                    'product' => (string) $row->product,
                    'quantity' => (int) $row->quantity,
                    'revenue' => $revenue,
                    'cost' => $cost,
                    'margin' => $revenue - $cost,
                    'margin_pct' => self::percent($revenue - $cost, $revenue),
                ];
            })
            ->all();
    }
}
