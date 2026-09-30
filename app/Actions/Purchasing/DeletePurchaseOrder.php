<?php

namespace App\Actions\Purchasing;

use App\Exceptions\BusinessRuleViolation;
use App\Models\PurchaseOrder;

/**
 * Only drafts can be deleted: once submitted, an order is part of the
 * record and is cancelled instead.
 */
final class DeletePurchaseOrder
{
    public function handle(PurchaseOrder $order): void
    {
        if (! $order->status->isEditable()) {
            throw new BusinessRuleViolation('Only draft purchase orders can be deleted. Cancel it instead.');
        }

        $order->delete();
    }
}
