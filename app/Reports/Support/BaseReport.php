<?php

namespace App\Reports\Support;

use App\Enums\SaleStatus;
use App\Reports\Column;
use App\Reports\Report;

/**
 * Shared helpers for report implementations.
 */
abstract class BaseReport implements Report
{
    /**
     * Sales whose stock has left and that count as revenue.
     *
     * @return list<string>
     */
    protected function completedSaleStatuses(): array
    {
        return [SaleStatus::Confirmed->value, SaleStatus::PartiallyPaid->value, SaleStatus::Paid->value];
    }

    /**
     * Sum every money/number column of the rows.
     *
     * @param  list<array<string, string|int|float|null>>  $rows
     * @return array<string, string|int|float|null>
     */
    public function totals(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $totals = [];
        foreach ($this->columns() as $index => $column) {
            $totals[$column->key] = match ($column->type) {
                Column::MONEY, Column::NUMBER => array_sum(array_map(fn (array $row) => (int) ($row[$column->key] ?? 0), $rows)),
                default => $index === 0 ? 'Total' : null,
            };
        }

        return $totals;
    }

    protected static function percent(int $part, int $whole): ?float
    {
        return $whole === 0 ? null : round($part / $whole * 100, 1);
    }
}
