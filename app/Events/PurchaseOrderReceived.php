<?php

namespace App\Events;

use App\Models\PurchaseReceipt;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class PurchaseOrderReceived implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly PurchaseReceipt $receipt) {}
}
