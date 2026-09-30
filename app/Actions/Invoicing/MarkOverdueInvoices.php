<?php

namespace App\Actions\Invoicing;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\SupplierBill;
use App\Support\Tenancy\TenantContext;

/**
 * Flags open customer invoices and supplier bills of the active company
 * whose due date has passed (in the company's time zone). Idempotent; run
 * daily by the scheduler.
 */
final class MarkOverdueInvoices
{
    public function __construct(private readonly TenantContext $tenant) {}

    /**
     * @return array{invoices: int, bills: int}
     */
    public function handle(): array
    {
        $today = now($this->tenant->companyOrFail()->timezone)->toDateString();
        $open = [InvoiceStatus::Issued->value, InvoiceStatus::PartiallyPaid->value];
        $changes = ['status' => InvoiceStatus::Overdue->value, 'updated_at' => now()];

        return [
            'invoices' => Invoice::whereIn('status', $open)->whereDate('due_date', '<', $today)->update($changes),
            'bills' => SupplierBill::whereIn('status', $open)->whereDate('due_date', '<', $today)->update($changes),
        ];
    }
}
