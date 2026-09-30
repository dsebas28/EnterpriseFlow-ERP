<?php

namespace App\Reports\Definitions;

use App\Enums\ExpenseStatus;
use App\Models\Expense;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Reports\Column;
use App\Reports\ReportFilters;
use App\Reports\Support\BaseReport;
use App\Reports\Support\DateBucket;

/**
 * Simplified P&L: net sales − cost of goods sold − approved expenses.
 * COGS uses the cost captured on each sale line at confirmation.
 */
final class ProfitAndLoss extends BaseReport
{
    public function key(): string
    {
        return 'profit';
    }

    public function title(): string
    {
        return 'Profit and loss';
    }

    public function description(): string
    {
        return 'Net sales, cost of goods sold, gross profit, expenses and net profit per period.';
    }

    public function group(): string
    {
        return 'Finance';
    }

    public function filters(): array
    {
        return ['from', 'to', 'group_by', 'warehouse_id'];
    }

    public function columns(): array
    {
        return [
            Column::text('period', 'Period'),
            Column::money('revenue', 'Net sales'),
            Column::money('cogs', 'Cost of goods sold'),
            Column::money('gross_profit', 'Gross profit'),
            Column::money('expenses', 'Expenses'),
            Column::money('net_profit', 'Net profit'),
            Column::percent('net_margin', 'Net margin %'),
        ];
    }

    public function rows(ReportFilters $filters): iterable
    {
        $saleBucket = DateBucket::sql('sales.sale_date', $filters->groupBy);
        $expenseBucket = DateBucket::sql('expense_date', $filters->groupBy);

        $revenue = Sale::query()
            ->whereIn('status', $this->completedSaleStatuses())
            ->whereDate('sale_date', '>=', $filters->from)
            ->whereDate('sale_date', '<=', $filters->to)
            ->when($filters->warehouseId, fn ($q, $id) => $q->where('warehouse_id', $id))
            ->selectRaw("{$saleBucket} as period, SUM(subtotal) as amount")
            ->groupByRaw($saleBucket)
            ->toBase()
            ->pluck('amount', 'period');

        $cogs = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->whereIn('sales.status', $this->completedSaleStatuses())
            ->whereDate('sales.sale_date', '>=', $filters->from)
            ->whereDate('sales.sale_date', '<=', $filters->to)
            ->when($filters->warehouseId, fn ($q, $id) => $q->where('sales.warehouse_id', $id))
            ->selectRaw("{$saleBucket} as period, SUM(sale_items.quantity * COALESCE(sale_items.unit_cost, 0)) as amount")
            ->groupByRaw($saleBucket)
            ->toBase()
            ->pluck('amount', 'period');

        $expenses = Expense::query()
            ->where('status', ExpenseStatus::Approved->value)
            ->whereDate('expense_date', '>=', $filters->from)
            ->whereDate('expense_date', '<=', $filters->to)
            ->selectRaw("{$expenseBucket} as period, SUM(amount) as amount")
            ->groupByRaw($expenseBucket)
            ->toBase()
            ->pluck('amount', 'period');

        $periods = $revenue->keys()->merge($expenses->keys())->unique()->sort()->values();

        return $periods->map(function ($period) use ($revenue, $cogs, $expenses): array {
            $sales = (int) ($revenue[$period] ?? 0);
            $cost = (int) ($cogs[$period] ?? 0);
            $spent = (int) ($expenses[$period] ?? 0);
            $net = $sales - $cost - $spent;

            return [
                'period' => (string) $period,
                'revenue' => $sales,
                'cogs' => $cost,
                'gross_profit' => $sales - $cost,
                'expenses' => $spent,
                'net_profit' => $net,
                'net_margin' => self::percent($net, $sales),
            ];
        })->all();
    }

    public function totals(array $rows): array
    {
        $totals = parent::totals($rows);

        if ($totals !== []) {
            $totals['net_margin'] = self::percent((int) $totals['net_profit'], (int) $totals['revenue']);
        }

        return $totals;
    }
}
