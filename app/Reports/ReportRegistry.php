<?php

namespace App\Reports;

use App\Reports\Definitions\ExpensesByCategory;
use App\Reports\Definitions\InventoryValuation;
use App\Reports\Definitions\LowStock;
use App\Reports\Definitions\PayablesAging;
use App\Reports\Definitions\ProfitAndLoss;
use App\Reports\Definitions\PurchasesByPeriod;
use App\Reports\Definitions\ReceivablesAging;
use App\Reports\Definitions\SalesByCustomer;
use App\Reports\Definitions\SalesByPeriod;
use App\Reports\Definitions\SalesByProduct;

/**
 * Catalogue of available reports, keyed by their URL-safe key.
 */
final class ReportRegistry
{
    /**
     * @var list<class-string<Report>>
     */
    private const REPORTS = [
        SalesByPeriod::class,
        SalesByProduct::class,
        SalesByCustomer::class,
        PurchasesByPeriod::class,
        ProfitAndLoss::class,
        ExpensesByCategory::class,
        ReceivablesAging::class,
        PayablesAging::class,
        InventoryValuation::class,
        LowStock::class,
    ];

    /**
     * @return list<Report>
     */
    public function all(): array
    {
        return array_map(fn (string $class) => app($class), self::REPORTS);
    }

    public function find(string $key): ?Report
    {
        foreach ($this->all() as $report) {
            if ($report->key() === $key) {
                return $report;
            }
        }

        return null;
    }

    public function findOrFail(string $key): Report
    {
        return $this->find($key) ?? abort(404);
    }
}
