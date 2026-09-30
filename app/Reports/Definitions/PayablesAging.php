<?php

namespace App\Reports\Definitions;

use App\Models\SupplierBill;
use App\Reports\ReportFilters;
use Illuminate\Support\Collection;

final class PayablesAging extends AgingReport
{
    public function key(): string
    {
        return 'payables-aging';
    }

    public function title(): string
    {
        return 'Accounts payable aging';
    }

    public function description(): string
    {
        return 'What is owed to suppliers, by how long it is past due.';
    }

    public function filters(): array
    {
        return ['supplier_id'];
    }

    protected function partyLabel(): string
    {
        return 'Supplier';
    }

    protected function openDocuments(ReportFilters $filters): Collection
    {
        return SupplierBill::query()
            ->join('suppliers', 'suppliers.id', '=', 'supplier_bills.supplier_id')
            ->whereIn('supplier_bills.status', $this->openStatuses())
            ->when($filters->supplierId, fn ($q, $id) => $q->where('supplier_bills.supplier_id', $id))
            ->selectRaw('suppliers.name as party, supplier_bills.due_date, supplier_bills.total - supplier_bills.amount_paid as balance')
            ->toBase()
            ->get()
            ->map(fn (object $row) => self::document($row));
    }
}
