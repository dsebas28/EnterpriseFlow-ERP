<?php

namespace App\Events;

use App\Models\Sale;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class SaleConfirmed implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly Sale $sale) {}
}
