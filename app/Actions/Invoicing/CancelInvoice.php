<?php

namespace App\Actions\Invoicing;

use App\Enums\InvoiceStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Voids an invoice. Its number stays used (issued numbers are never
 * reused) and the reason is kept; the sale can then be invoiced again.
 */
final class CancelInvoice
{
    public function handle(Invoice $invoice, User $user, string $reason): Invoice
    {
        return DB::transaction(function () use ($invoice, $user, $reason): Invoice {
            $invoice = Invoice::lockForUpdate()->findOrFail($invoice->id);

            if ($invoice->amount_paid > 0) {
                throw new BusinessRuleViolation('This invoice has payments and cannot be cancelled. Issue a credit note or refund instead.');
            }

            $invoice->transitionTo(InvoiceStatus::Cancelled);
            $invoice->forceFill([
                'cancelled_by' => $user->id,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ])->save();

            return $invoice;
        });
    }
}
