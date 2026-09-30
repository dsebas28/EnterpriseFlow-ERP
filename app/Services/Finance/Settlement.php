<?php

namespace App\Services\Finance;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\SaleStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\SupplierBill;
use App\Support\Tenancy\TenantContext;

/**
 * Recomputes what has been paid on a document from its posted payments and
 * derives the payment status from the amounts.
 *
 * amount_paid is always SUM(posted payments), never incremented or
 * decremented in place, so it cannot drift from the payment history.
 * Must run inside the transaction that locked the document.
 */
final class Settlement
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function settleInvoice(Invoice $invoice): void
    {
        $paid = $this->postedTotal('invoice_id', $invoice->id);

        $invoice->forceFill([
            'amount_paid' => $paid,
            'status' => InvoiceStatus::fromSettlement($invoice->total, $paid, $invoice->due_date->toDateString(), $this->today()),
        ])->save();

        $this->syncSale($invoice, $paid);
    }

    public function settleBill(SupplierBill $bill): void
    {
        $paid = $this->postedTotal('supplier_bill_id', $bill->id);

        $bill->forceFill([
            'amount_paid' => $paid,
            'status' => InvoiceStatus::fromSettlement($bill->total, $paid, $bill->due_date->toDateString(), $this->today()),
        ])->save();
    }

    /**
     * The sale mirrors the payments of its (single active) invoice.
     */
    private function syncSale(Invoice $invoice, int $paid): void
    {
        $sale = Sale::lockForUpdate()->findOrFail($invoice->sale_id);

        if (! $sale->status->hasDeductedStock()) {
            return;
        }

        $sale->forceFill([
            'amount_paid' => $paid,
            'status' => match (true) {
                $paid >= $sale->total => SaleStatus::Paid,
                $paid > 0 => SaleStatus::PartiallyPaid,
                default => SaleStatus::Confirmed,
            },
        ])->save();
    }

    private function postedTotal(string $column, string $documentId): int
    {
        return (int) Payment::query()
            ->where($column, $documentId)
            ->where('status', PaymentStatus::Posted->value)
            ->sum('amount');
    }

    private function today(): string
    {
        return now($this->tenant->companyOrFail()->timezone)->toDateString();
    }
}
