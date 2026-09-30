<?php

namespace App\Reports\Definitions;

use App\Models\Sale;
use App\Reports\Column;
use App\Reports\ReportFilters;
use App\Reports\Support\BaseReport;
use App\Reports\Support\DateBucket;

final class SalesByPeriod extends BaseReport
{
    public function key(): string
    {
        return 'sales-by-period';
    }

    public function title(): string
    {
        return 'Sales by period';
    }

    public function description(): string
    {
        return 'Confirmed sales grouped by day, week or month.';
    }

    public function group(): string
    {
        return 'Sales';
    }

    public function filters(): array
    {
        return ['from', 'to', 'group_by', 'warehouse_id', 'customer_id', 'user_id'];
    }

    public function columns(): array
    {
        return [
            Column::text('period', 'Period'),
            Column::number('orders', 'Orders'),
            Column::money('discounts', 'Discounts'),
            Column::money('net', 'Net sales'),
            Column::money('tax', 'Tax'),
            Column::money('total', 'Total'),
        ];
    }

    public function rows(ReportFilters $filters): iterable
    {
        $bucket = DateBucket::sql('sale_date', $filters->groupBy);

        return Sale::query()
            ->whereIn('status', $this->completedSaleStatuses())
            ->whereDate('sale_date', '>=', $filters->from)
            ->whereDate('sale_date', '<=', $filters->to)
            ->when($filters->warehouseId, fn ($q, $id) => $q->where('warehouse_id', $id))
            ->when($filters->customerId, fn ($q, $id) => $q->where('customer_id', $id))
            ->when($filters->userId, fn ($q, $id) => $q->where('created_by', $id))
            ->selectRaw("{$bucket} as period, COUNT(*) as orders, SUM(discount_total) as discounts, SUM(subtotal) as net, SUM(tax_total) as tax, SUM(total) as total")
            ->groupByRaw($bucket)
            ->orderByRaw($bucket)
            ->toBase()
            ->get()
            ->map(fn (object $row) => [
                'period' => (string) $row->period,
                'orders' => (int) $row->orders,
                'discounts' => (int) $row->discounts,
                'net' => (int) $row->net,
                'tax' => (int) $row->tax,
                'total' => (int) $row->total,
            ])
            ->all();
    }
}
