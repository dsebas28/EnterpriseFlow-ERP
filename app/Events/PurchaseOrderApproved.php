<?php

namespace App\Events;

use App\Models\PurchaseOrder;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class PurchaseOrderApproved implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly PurchaseOrder $order) {}
}
