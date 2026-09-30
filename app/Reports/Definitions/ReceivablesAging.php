<?php

namespace App\Reports\Definitions;

use App\Models\Invoice;
use App\Reports\ReportFilters;
use Illuminate\Support\Collection;

final class ReceivablesAging extends AgingReport
{
    public function key(): string
    {
        return 'receivables-aging';
    }

    public function title(): string
    {
        return 'Accounts receivable aging';
    }

    public function description(): string
    {
        return 'What customers owe, by how long it is past due.';
    }

    public function filters(): array
    {
        return ['customer_id'];
    }

    protected function partyLabel(): string
    {
        return 'Customer';
    }

    protected function openDocuments(ReportFilters $filters): Collection
    {
        return Invoice::query()
            ->join('customers', 'customers.id', '=', 'invoices.customer_id')
            ->whereIn('invoices.status', $this->openStatuses())
            ->when($filters->customerId, fn ($q, $id) => $q->where('invoices.customer_id', $id))
            ->selectRaw('customers.name as party, invoices.due_date, invoices.total - invoices.amount_paid as balance')
            ->toBase()
            ->get()
            ->map(fn (object $row) => self::document($row));
    }
}
