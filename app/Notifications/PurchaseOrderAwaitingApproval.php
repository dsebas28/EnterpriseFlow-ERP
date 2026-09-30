<?php

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Models\Company;
use App\Models\PurchaseOrder;
use App\Support\Money\MoneyFormatter;

class PurchaseOrderAwaitingApproval extends CompanyNotification
{
    public function __construct(Company $company, PurchaseOrder $order)
    {
        parent::__construct(
            $company,
            title: "Purchase order {$order->number} awaits approval",
            body: sprintf('%s · %s', $order->supplier->name ?? 'Supplier', MoneyFormatter::format($order->money($order->total))),
            url: route('purchasing.orders.show', $order),
        );
    }

    public function category(): NotificationCategory
    {
        return NotificationCategory::PurchaseApprovalRequested;
    }
}
