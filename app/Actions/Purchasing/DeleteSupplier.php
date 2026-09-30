<?php

namespace App\Actions\Purchasing;

use App\Enums\PurchaseOrderStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Supplier;

/**
 * Soft-deletes a supplier. Closed orders keep pointing at it; open ones
 * must be completed or cancelled first.
 */
final class DeleteSupplier
{
    public function handle(Supplier $supplier): void
    {
        $hasOpenOrders = $supplier->purchaseOrders()
            ->whereNotIn('status', [PurchaseOrderStatus::Received->value, PurchaseOrderStatus::Cancelled->value])
            ->exists();

        if ($hasOpenOrders) {
            throw new BusinessRuleViolation('This supplier has open purchase orders. Complete or cancel them first.');
        }

        $supplier->delete();
    }
}
