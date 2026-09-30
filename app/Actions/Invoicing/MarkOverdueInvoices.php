<?php

namespace App\Actions\Invoicing;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Support\Tenancy\TenantContext;

/**
 * Flags open invoices of the active company whose due date has passed
 * (in the company's time zone). Idempotent; run daily by the scheduler.
 */
final class MarkOverdueInvoices
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function handle(): int
    {
        $today = now($this->tenant->companyOrFail()->timezone)->toDateString();

        return Invoice::query()
            ->whereIn('status', [InvoiceStatus::Issued->value, InvoiceStatus::PartiallyPaid->value])
            ->whereDate('due_date', '<', $today)
            ->update(['status' => InvoiceStatus::Overdue->value, 'updated_at' => now()]);
    }
}
