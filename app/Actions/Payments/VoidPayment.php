<?php

namespace App\Actions\Payments;

use App\Enums\PaymentStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SupplierBill;
use App\Models\User;
use App\Services\Finance\Settlement;
use Illuminate\Support\Facades\DB;

/**
 * Voids a payment recorded by mistake. The payment stays in the history
 * (marked voided, with who, when and why) and the document's balance and
 * status are recomputed from the remaining posted payments.
 */
final class VoidPayment
{
    public function __construct(private readonly Settlement $settlement) {}

    public function handle(Payment $payment, User $user, string $reason): Payment
    {
        return DB::transaction(function () use ($payment, $user, $reason): Payment {
            // Lock the document first (same order as RecordPayment) to avoid deadlocks.
            $document = $payment->invoice_id !== null
                ? Invoice::lockForUpdate()->findOrFail($payment->invoice_id)
                : SupplierBill::lockForUpdate()->findOrFail($payment->supplier_bill_id);

            $payment = Payment::lockForUpdate()->findOrFail($payment->id);

            if ($payment->isVoided()) {
                throw new BusinessRuleViolation("Payment {$payment->number} is already voided.");
            }

            $payment->forceFill([
                'status' => PaymentStatus::Voided,
                'voided_by' => $user->id,
                'voided_at' => now(),
                'void_reason' => $reason,
            ])->save();

            $document instanceof Invoice
                ? $this->settlement->settleInvoice($document)
                : $this->settlement->settleBill($document);

            return $payment;
        });
    }
}
