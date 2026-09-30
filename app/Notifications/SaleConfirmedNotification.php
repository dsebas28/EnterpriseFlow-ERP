<?php

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Models\Company;
use App\Models\Sale;
use App\Support\Money\MoneyFormatter;

class SaleConfirmedNotification extends CompanyNotification
{
    public function __construct(Company $company, Sale $sale)
    {
        parent::__construct(
            $company,
            title: "New sale {$sale->number}",
            body: sprintf('%s bought %s.', $sale->customer->name ?? 'A customer', MoneyFormatter::format($sale->money($sale->total))),
            url: route('sales.orders.show', $sale),
            level: 'success',
        );
    }

    public function category(): NotificationCategory
    {
        return NotificationCategory::SaleConfirmed;
    }
}
