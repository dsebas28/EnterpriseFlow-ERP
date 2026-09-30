<?php

namespace App\Reports\Definitions;

use App\Enums\ExpenseStatus;
use App\Models\Expense;
use App\Reports\Column;
use App\Reports\ReportFilters;
use App\Reports\Support\BaseReport;

final class ExpensesByCategory extends BaseReport
{
    public function key(): string
    {
        return 'expenses-by-category';
    }

    public function title(): string
    {
        return 'Expenses by category';
    }

    public function description(): string
    {
        return 'Approved expenses per category.';
    }

    public function group(): string
    {
        return 'Finance';
    }

    public function filters(): array
    {
        return ['from', 'to', 'expense_category_id', 'supplier_id', 'user_id'];
    }

    public function columns(): array
    {
        return [
            Column::text('category', 'Category'),
            Column::number('count', 'Expenses'),
            Column::money('total', 'Total'),
            Column::percent('share', 'Share %'),
        ];
    }

    public function rows(ReportFilters $filters): iterable
    {
        $rows = Expense::query()
            ->join('expense_categories', 'expense_categories.id', '=', 'expenses.category_id')
            ->where('expenses.status', ExpenseStatus::Approved->value)
            ->whereDate('expenses.expense_date', '>=', $filters->from)
            ->whereDate('expenses.expense_date', '<=', $filters->to)
            ->when($filters->expenseCategoryId, fn ($q, $id) => $q->where('expenses.category_id', $id))
            ->when($filters->supplierId, fn ($q, $id) => $q->where('expenses.supplier_id', $id))
            ->when($filters->userId, fn ($q, $id) => $q->where('expenses.created_by', $id))
            ->selectRaw('expense_categories.name as category, COUNT(*) as count, SUM(expenses.amount) as total')
            ->groupBy('expense_categories.id', 'expense_categories.name')
            ->orderByDesc('total')
            ->toBase()
            ->get();

        $grandTotal = (int) $rows->sum('total');

        return $rows->map(fn (object $row) => [
            'category' => (string) $row->category,
            'count' => (int) $row->count,
            'total' => (int) $row->total,
            'share' => self::percent((int) $row->total, $grandTotal),
        ])->all();
    }
}
