<?php

namespace App\Reports\Definitions;

use App\Enums\SaleStatus;
use App\Models\Sale;
use App\Reports\Column;
use App\Reports\ReportFilters;
use App\Reports\Support\BaseReport;

final class SalesByCustomer extends BaseReport
{
    public function key(): string
    {
        return 'sales-by-customer';
    }

    public function title(): string
    {
        return 'Sales by customer';
    }

    public function description(): string
    {
        return 'Orders, revenue and outstanding balance per customer.';
    }

    public function group(): string
    {
        return 'Sales';
    }

    public function filters(): array
    {
        return ['from', 'to', 'customer_id', 'user_id'];
    }

    public function columns(): array
    {
        return [
            Column::text('customer', 'Customer'),
            Column::number('orders', 'Orders'),
            Column::money('net', 'Net sales'),
            Column::money('total', 'Total'),
            Column::money('outstanding', 'Outstanding'),
        ];
    }

    public function rows(ReportFilters $filters): iterable
    {
        $receivable = "'".implode("','", SaleStatus::receivableValues())."'";

        return Sale::query()
            ->join('customers', 'customers.id', '=', 'sales.customer_id')
            ->whereIn('sales.status', $this->completedSaleStatuses())
            ->whereDate('sales.sale_date', '>=', $filters->from)
            ->whereDate('sales.sale_date', '<=', $filters->to)
            ->when($filters->customerId, fn ($q, $id) => $q->where('sales.customer_id', $id))
            ->when($filters->userId, fn ($q, $id) => $q->where('sales.created_by', $id))
            ->selectRaw("customers.name as customer, COUNT(*) as orders, SUM(sales.subtotal) as net, SUM(sales.total) as total, SUM(CASE WHEN sales.status IN ({$receivable}) THEN sales.total - sales.amount_paid ELSE 0 END) as outstanding")
            ->groupBy('customers.id', 'customers.name')
            ->orderByDesc('total')
            ->toBase()
            ->get()
            ->map(fn (object $row) => [
                'customer' => (string) $row->customer,
                'orders' => (int) $row->orders,
                'net' => (int) $row->net,
                'total' => (int) $row->total,
                'outstanding' => (int) $row->outstanding,
            ])
            ->all();
    }
}
