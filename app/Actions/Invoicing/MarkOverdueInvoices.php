<?php

namespace App\Actions\Invoicing;

use App\Enums\InvoiceStatus;
use App\Events\InvoicesBecameOverdue;
use App\Models\Invoice;
use App\Models\SupplierBill;
use App\Support\Tenancy\TenantContext;

/**
 * Flags open customer invoices and supplier bills of the active company
 * whose due date has passed (in the company's time zone). Idempotent; run
 * hourly by the scheduler.
 */
final class MarkOverdueInvoices
{
    public function __construct(private readonly TenantContext $tenant) {}

    /**
     * @return array{invoices: int, bills: int}
     */
    public function handle(): array
    {
        $company = $this->tenant->companyOrFail();
        $today = now($company->timezone)->toDateString();
        $open = [InvoiceStatus::Issued->value, InvoiceStatus::PartiallyPaid->value];
        $changes = ['status' => InvoiceStatus::Overdue->value, 'updated_at' => now()];

        // Only invoices that change in *this* run are announced, so the
        // hourly schedule never repeats a notification.
        /** @var list<string> $newlyOverdue */
        $newlyOverdue = Invoice::whereIn('status', $open)->whereDate('due_date', '<', $today)->pluck('id')->all();

        $invoices = $newlyOverdue === []
            ? 0
            // Status re-checked: a payment may have settled one meanwhile.
            : Invoice::whereKey($newlyOverdue)->whereIn('status', $open)->update($changes);

        if ($invoices > 0) {
            InvoicesBecameOverdue::dispatch($company, $newlyOverdue);
        }

        return [
            'invoices' => $invoices,
            'bills' => SupplierBill::whereIn('status', $open)->whereDate('due_date', '<', $today)->update($changes),
        ];
    }
}
