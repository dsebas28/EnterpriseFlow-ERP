<?php

namespace App\Actions\Invoicing;

use App\Enums\InvoiceStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Models\PurchaseOrderItem;
use App\Models\SupplierBill;
use App\Models\SupplierBillItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Voids a supplier bill registered by mistake and releases its billed
 * quantities so the correct bill can be registered.
 */
final class CancelSupplierBill
{
    public function handle(SupplierBill $bill, User $user, string $reason): SupplierBill
    {
        return DB::transaction(function () use ($bill, $user, $reason): SupplierBill {
            $bill = SupplierBill::lockForUpdate()->findOrFail($bill->id);

            if ($bill->amount_paid > 0) {
                throw new BusinessRuleViolation('This bill has payments and cannot be cancelled.');
            }

            $bill->transitionTo(InvoiceStatus::Cancelled);

            $bill->items()->get()->each(function (SupplierBillItem $line): void {
                $item = PurchaseOrderItem::lockForUpdate()->findOrFail($line->purchase_order_item_id);
                $item->forceFill(['billed_quantity' => max(0, $item->billed_quantity - $line->quantity)])->save();
            });

            $bill->forceFill([
                'cancelled_by' => $user->id,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ])->save();

            return $bill;
        });
    }
}
