<?php

namespace App\Actions\Payments;

use App\DTOs\PaymentData;
use App\Enums\DocumentType;
use App\Enums\PaymentDirection;
use App\Enums\PaymentStatus;
use App\Events\PaymentReceived;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SupplierBill;
use App\Models\User;
use App\Services\Documents\DocumentNumberGenerator;
use App\Services\Finance\Settlement;
use App\Support\Money\MoneyFormatter;
use Illuminate\Support\Facades\DB;

/**
 * Records money received against an invoice or paid against a supplier
 * bill. The document row is locked, so two payments recorded at the same
 * time can never exceed its balance.
 *
 * `$user` is null for payments recorded by the system (payment gateway
 * webhooks); the audit trail then shows no human actor.
 */
final class RecordPayment
{
    public function __construct(
        private readonly DocumentNumberGenerator $numbers,
        private readonly Settlement $settlement,
    ) {}

    public function handle(Invoice|SupplierBill $document, PaymentData $data, ?User $user): Payment
    {
        if ($data->amount <= 0) {
            throw new BusinessRuleViolation('The payment amount must be greater than zero.');
        }

        $payment = DB::transaction(function () use ($document, $data, $user): Payment {
            $document = $document::lockForUpdate()->findOrFail($document->id);

            if (! $document->status->isOpen()) {
                throw new BusinessRuleViolation("Payments can only be recorded on open documents ({$document->status->label()}).");
            }

            $balance = $document->balanceDue();
            if ($data->amount > $balance) {
                throw new BusinessRuleViolation(sprintf(
                    'The payment exceeds the balance due of %s.',
                    MoneyFormatter::format($document->money($balance)),
                ));
            }

            $isInvoice = $document instanceof Invoice;

            $payment = new Payment;
            $payment->forceFill([
                'number' => $this->numbers->next(DocumentType::Payment),
                'direction' => $isInvoice ? PaymentDirection::Incoming : PaymentDirection::Outgoing,
                'invoice_id' => $isInvoice ? $document->id : null,
                'supplier_bill_id' => $isInvoice ? null : $document->id,
                'method' => $data->method,
                'amount' => $data->amount,
                'currency' => $document->currency,
                'paid_at' => $data->paidAt,
                'reference' => $data->reference,
                'notes' => $data->notes,
                'status' => PaymentStatus::Posted,
                'created_by' => $user?->id,
            ])->save();

            $isInvoice ? $this->settlement->settleInvoice($document) : $this->settlement->settleBill($document);

            return $payment;
        });

        if ($payment->direction === PaymentDirection::Incoming) {
            PaymentReceived::dispatch($payment);
        }

        return $payment;
    }
}
