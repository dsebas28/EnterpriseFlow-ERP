<?php

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Models\Company;
use App\Models\Payment;
use App\Support\Money\MoneyFormatter;

class PaymentReceivedNotification extends CompanyNotification
{
    public function __construct(Company $company, Payment $payment)
    {
        $invoice = $payment->invoice;

        parent::__construct(
            $company,
            title: 'Payment received: '.MoneyFormatter::format($payment->money()),
            body: sprintf(
                '%s for invoice %s%s.',
                $payment->number,
                $invoice->number ?? '—',
                $invoice?->customer ? " from {$invoice->customer->name}" : '',
            ),
            url: $invoice ? route('finance.invoices.show', $invoice) : null,
            level: 'success',
        );
    }

    public function category(): NotificationCategory
    {
        return NotificationCategory::PaymentReceived;
    }
}
