<?php

namespace App\Reports;

/**
 * A report definition, shared by the web UI, exports and the API.
 * Implementations query the active company only (tenant-scoped models).
 */
interface Report
{
    public function key(): string;

    public function title(): string;

    public function description(): string;

    /**
     * Section of the reports catalogue (Sales, Purchasing, Finance, Inventory).
     */
    public function group(): string;

    /**
     * Filters this report honours: from/to, group_by, warehouse_id,
     * category_id, expense_category_id, customer_id, supplier_id, user_id.
     *
     * @return list<string>
     */
    public function filters(): array;

    /**
     * @return list<Column>
     */
    public function columns(): array;

    /**
     * @return iterable<int, array<string, string|int|float|null>>
     */
    public function rows(ReportFilters $filters): iterable;

    /**
     * Totals row keyed like columns (empty when not meaningful).
     *
     * @param  list<array<string, string|int|float|null>>  $rows
     * @return array<string, string|int|float|null>
     */
    public function totals(array $rows): array;
}
