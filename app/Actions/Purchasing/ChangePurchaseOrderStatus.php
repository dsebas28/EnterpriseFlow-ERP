<?php

namespace App\Actions\Purchasing;

use App\Enums\PurchaseOrderStatus;
use App\Events\PurchaseOrderApproved;
use App\Exceptions\BusinessRuleViolation;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Submit, approve, return to draft or cancel a purchase order. Each call
 * locks the order row, so two approvers clicking at the same time cannot
 * both succeed, and every transition is validated by the state machine.
 */
final class ChangePurchaseOrderStatus
{
    public function submit(PurchaseOrder $order): PurchaseOrder
    {
        return $this->transition($order, PurchaseOrderStatus::Pending, function (PurchaseOrder $locked): void {
            if (! $locked->items()->exists()) {
                throw new BusinessRuleViolation('Add at least one line before submitting the order.');
            }
        });
    }

    public function approve(PurchaseOrder $order, User $approver): PurchaseOrder
    {
        $approved = $this->transition($order, PurchaseOrderStatus::Approved, function (PurchaseOrder $locked) use ($approver): void {
            $locked->forceFill(['approved_by' => $approver->id, 'approved_at' => now()]);
        });

        PurchaseOrderApproved::dispatch($approved);

        return $approved;
    }

    public function returnToDraft(PurchaseOrder $order): PurchaseOrder
    {
        return $this->transition($order, PurchaseOrderStatus::Draft);
    }

    public function cancel(PurchaseOrder $order, User $user, string $reason): PurchaseOrder
    {
        return $this->transition($order, PurchaseOrderStatus::Cancelled, function (PurchaseOrder $locked) use ($user, $reason): void {
            $locked->forceFill([
                'cancelled_by' => $user->id,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ]);
        });
    }

    /**
     * @param  (callable(PurchaseOrder): void)|null  $before  extra checks/changes on the locked row
     */
    private function transition(PurchaseOrder $order, PurchaseOrderStatus $target, ?callable $before = null): PurchaseOrder
    {
        return DB::transaction(function () use ($order, $target, $before): PurchaseOrder {
            $locked = PurchaseOrder::lockForUpdate()->findOrFail($order->id);

            $locked->transitionTo($target);

            if ($before !== null) {
                $before($locked);
            }

            $locked->save();

            return $locked;
        });
    }
}
