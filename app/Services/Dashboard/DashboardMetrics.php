<?php

namespace App\Services\Dashboard;

use App\Enums\ExpenseStatus;
use App\Enums\InvoiceStatus;
use App\Enums\SaleStatus;
use App\Http\Resources\MoneyResource;
use App\Models\Company;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Models\SupplierBill;
use App\Reports\Definitions\LowStock;
use App\Reports\Definitions\ProfitAndLoss;
use App\Reports\Definitions\SalesByPeriod;
use App\Reports\Definitions\SalesByProduct;
use App\Reports\ReportFilters;
use App\Support\Money\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Company-wide dashboard figures, computed from real transactions and
 * reusing the report definitions (one source of truth for each number).
 *
 * Cached briefly per company and day: the dashboard is the most visited
 * page and these aggregates do not need to be real-time to the second.
 * Sections are filtered per user permissions by the caller, never here.
 */
final class DashboardMetrics
{
    public const TTL_SECONDS = 60;

    public const CHART_DAYS = 30;

    public function __construct(
        private readonly SalesByPeriod $salesByPeriod,
        private readonly SalesByProduct $salesByProduct,
        private readonly ProfitAndLoss $profit,
        private readonly LowStock $lowStock,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function for(Company $company): array
    {
        $today = now($company->timezone)->toDateString();

        return Cache::remember(
            "dashboard:{$company->id}:{$today}",
            self::TTL_SECONDS,
            fn () => $this->compute($company, $today),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function compute(Company $company, string $today): array
    {
        $money = fn (int $minor) => MoneyResource::make(new Money($minor, $company->currency));
        $monthStart = substr($today, 0, 8).'01';
        $completed = [SaleStatus::Confirmed->value, SaleStatus::PartiallyPaid->value, SaleStatus::Paid->value];

        $salesToday = Sale::whereIn('status', $completed)->whereDate('sale_date', $today);
        $salesMonth = Sale::whereIn('status', $completed)->whereDate('sale_date', '>=', $monthStart)->whereDate('sale_date', '<=', $today);

        $monthFilters = new ReportFilters(from: $monthStart, to: $today, groupBy: 'month');
        $profitRow = collect($this->profit->rows($monthFilters))->first();

        $lowStock = collect($this->lowStock->rows(new ReportFilters(from: $today, to: $today)));

        $receivable = Invoice::whereIn('status', InvoiceStatus::openValues());
        $payable = SupplierBill::whereIn('status', InvoiceStatus::openValues());

        return [
            'sales' => [
                'today' => $money((int) (clone $salesToday)->sum('total')),
                'today_count' => (clone $salesToday)->count(),
                'month' => $money((int) (clone $salesMonth)->sum('total')),
                'month_count' => (clone $salesMonth)->count(),
                'chart' => $this->dailySales($today),
                'top_products' => collect($this->salesByProduct->rows($monthFilters))
                    ->take(5)
                    ->map(fn (array $row) => ['product' => $row['product'], 'quantity' => $row['quantity'], 'revenue' => $money((int) $row['revenue'])])
                    ->values()
                    ->all(),
                'recent' => Sale::with('customer:id,name')
                    ->whereIn('status', [...$completed, SaleStatus::Pending->value])
                    ->latest('sale_date')->latest('number')->limit(5)->get()
                    ->map(fn (Sale $s) => [
                        'id' => $s->id, 'number' => $s->number, 'customer' => $s->customer->name,
                        'status' => $s->status->value, 'status_label' => $s->status->label(), 'total' => $money($s->total),
                    ])->all(),
            ],
            'finance' => [
                'expenses_month' => $money((int) Expense::where('status', ExpenseStatus::Approved->value)
                    ->whereDate('expense_date', '>=', $monthStart)->whereDate('expense_date', '<=', $today)->sum('amount')),
                'profit_month' => $money((int) ($profitRow['net_profit'] ?? 0)),
                'gross_profit_month' => $money((int) ($profitRow['gross_profit'] ?? 0)),
                'receivable' => $money((int) (clone $receivable)->sum('total') - (int) (clone $receivable)->sum('amount_paid')),
                'receivable_overdue' => (clone $receivable)->where('status', InvoiceStatus::Overdue->value)->count(),
                'payable' => $money((int) (clone $payable)->sum('total') - (int) (clone $payable)->sum('amount_paid')),
                'payable_overdue' => (clone $payable)->where('status', InvoiceStatus::Overdue->value)->count(),
            ],
            'inventory' => [
                'low_stock' => $lowStock->take(6)->values()->all(),
                'low_stock_count' => $lowStock->count(),
            ],
            'purchases' => [
                'recent' => PurchaseOrder::with('supplier:id,name')->latest('order_date')->latest('number')->limit(5)->get()
                    ->map(fn (PurchaseOrder $o) => [
                        'id' => $o->id, 'number' => $o->number, 'supplier' => $o->supplier->name,
                        'status' => $o->status->value, 'status_label' => $o->status->label(), 'total' => $money($o->total),
                    ])->all(),
            ],
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * One point per day for the last CHART_DAYS days, zero-filled.
     *
     * @return list<array{date: string, total: int}>
     */
    private function dailySales(string $today): array
    {
        $from = Carbon::parse($today)->subDays(self::CHART_DAYS - 1)->toDateString();
        $totals = collect($this->salesByPeriod->rows(new ReportFilters(from: $from, to: $today, groupBy: 'day')))
            ->pluck('total', 'period');

        $points = [];
        for ($date = Carbon::parse($from); $date->toDateString() <= $today; $date->addDay()) {
            $key = $date->toDateString();
            $points[] = ['date' => $key, 'total' => (int) ($totals[$key] ?? 0)];
        }

        return $points;
    }
}
