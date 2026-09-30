<?php

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Models\Company;
use App\Models\PurchaseOrder;

class PurchaseOrderApprovedNotification extends CompanyNotification
{
    public function __construct(Company $company, PurchaseOrder $order)
    {
        $expected = $order->expected_date ? ' Expected on '.$order->expected_date->toFormattedDayDateString().'.' : '';

        parent::__construct(
            $company,
            title: "Purchase order {$order->number} approved",
            body: sprintf('Goods from %s can be received.%s', $order->supplier->name ?? 'the supplier', $expected),
            url: route('purchasing.orders.show', $order),
            level: 'success',
        );
    }

    public function category(): NotificationCategory
    {
        return NotificationCategory::PurchaseOrderApproved;
    }
}
