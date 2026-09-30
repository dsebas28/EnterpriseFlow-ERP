<?php

namespace App\Reports\Definitions;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Reports\Column;
use App\Reports\ReportFilters;
use App\Reports\Support\BaseReport;
use App\Reports\Support\DateBucket;

final class PurchasesByPeriod extends BaseReport
{
    public function key(): string
    {
        return 'purchases-by-period';
    }

    public function title(): string
    {
        return 'Purchases by period';
    }

    public function description(): string
    {
        return 'Approved purchase orders grouped by day, week or month.';
    }

    public function group(): string
    {
        return 'Purchasing';
    }

    public function filters(): array
    {
        return ['from', 'to', 'group_by', 'supplier_id', 'warehouse_id'];
    }

    public function columns(): array
    {
        return [
            Column::text('period', 'Period'),
            Column::number('orders', 'Orders'),
            Column::money('net', 'Net'),
            Column::money('tax', 'Tax'),
            Column::money('total', 'Total'),
        ];
    }

    public function rows(ReportFilters $filters): iterable
    {
        $bucket = DateBucket::sql('order_date', $filters->groupBy);

        return PurchaseOrder::query()
            ->whereIn('status', [
                PurchaseOrderStatus::Approved->value,
                PurchaseOrderStatus::PartiallyReceived->value,
                PurchaseOrderStatus::Received->value,
            ])
            ->whereDate('order_date', '>=', $filters->from)
            ->whereDate('order_date', '<=', $filters->to)
            ->when($filters->supplierId, fn ($q, $id) => $q->where('supplier_id', $id))
            ->when($filters->warehouseId, fn ($q, $id) => $q->where('warehouse_id', $id))
            ->selectRaw("{$bucket} as period, COUNT(*) as orders, SUM(subtotal) as net, SUM(tax_total) as tax, SUM(total) as total")
            ->groupByRaw($bucket)
            ->orderByRaw($bucket)
            ->toBase()
            ->get()
            ->map(fn (object $row) => [
                'period' => (string) $row->period,
                'orders' => (int) $row->orders,
                'net' => (int) $row->net,
                'tax' => (int) $row->tax,
                'total' => (int) $row->total,
            ])
            ->all();
    }
}
