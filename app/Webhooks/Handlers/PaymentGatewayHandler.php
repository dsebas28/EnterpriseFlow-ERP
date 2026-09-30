<?php

namespace App\Webhooks\Handlers;

use App\Actions\Payments\RecordPayment;
use App\DTOs\PaymentData;
use App\Enums\PaymentMethod;
use App\Enums\WebhookEventStatus;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\WebhookEvent;
use App\Support\Tenancy\TenantContext;
use App\Webhooks\Contracts\WebhookHandler;
use App\Webhooks\UnprocessableWebhook;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Card payments collected by an external gateway (e.g. a payment link sent
 * with the invoice).
 *
 *     {
 *       "id": "evt_123", "type": "payment.succeeded",
 *       "data": { "company_id": "...", "invoice_id": "...", "amount": 100000,
 *                 "currency": "COP", "paid_at": "2026-09-29", "reference": "ch_abc" }
 *     }
 *
 * `amount` is in minor units. The invoice is looked up inside the company
 * named by the event, so an event can never touch another tenant's data.
 */
final class PaymentGatewayHandler implements WebhookHandler
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly RecordPayment $recordPayment,
    ) {}

    public function handle(WebhookEvent $event): WebhookEventStatus
    {
        if ($event->type !== 'payment.succeeded') {
            // e.g. payment.failed: informational, the invoice stays open.
            return WebhookEventStatus::Ignored;
        }

        $validator = Validator::make($event->payload['data'] ?? [], [
            'company_id' => ['required', 'string'],
            'invoice_id' => ['required', 'string'],
            'amount' => ['required', 'integer', 'min:1'],
            'currency' => ['required', 'string', 'size:3'],
            'paid_at' => ['nullable', 'date_format:Y-m-d'],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);

        if ($validator->fails()) {
            throw new UnprocessableWebhook('Invalid payment data: '.$validator->errors()->first());
        }

        /** @var array{company_id: string, invoice_id: string, amount: int, currency: string, paid_at?: string|null, reference?: string|null} $data */
        $data = $validator->validated();

        $company = Company::find($data['company_id'])
            ?? throw new UnprocessableWebhook('Unknown company.');

        $event->company_id = $company->id;

        $this->tenant->run($company, function () use ($event, $data): void {
            // Tenant-scoped lookup: an invoice of another company is "not found".
            $invoice = Invoice::find($data['invoice_id'])
                ?? throw new UnprocessableWebhook('Unknown invoice.');

            if ($invoice->currency !== strtoupper($data['currency'])) {
                throw new UnprocessableWebhook("Currency mismatch: invoice is in {$invoice->currency}.");
            }

            $this->recordPayment->handle($invoice, new PaymentData(
                amount: $data['amount'],
                method: PaymentMethod::Card,
                paidAt: $data['paid_at'] ?? now($this->tenant->companyOrFail()->timezone)->toDateString(),
                reference: Str::limit($data['reference'] ?? $event->external_id, 100, ''),
                notes: "Payment gateway event {$event->external_id}",
            ), null);
        });

        return WebhookEventStatus::Processed;
    }
}
