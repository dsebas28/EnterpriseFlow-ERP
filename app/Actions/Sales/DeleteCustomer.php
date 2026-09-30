<?php

namespace App\Actions\Sales;

use App\Enums\SaleStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Customer;

/**
 * Soft-deletes a customer. Past sales keep pointing at it; customers with
 * open sales or an outstanding balance cannot be removed.
 */
final class DeleteCustomer
{
    public function handle(Customer $customer): void
    {
        $blocking = $customer->sales()
            ->whereIn('status', [SaleStatus::Draft->value, SaleStatus::Pending->value, ...SaleStatus::receivableValues()])
            ->exists();

        if ($blocking) {
            throw new BusinessRuleViolation('This customer has open sales or an outstanding balance.');
        }

        $customer->delete();
    }
}
