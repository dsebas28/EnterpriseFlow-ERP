<?php

namespace App\Events;

use App\Models\StockMovement;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched only after the surrounding transaction commits, so listeners
 * never react to a movement that was rolled back.
 */
class StockMovementRecorded implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly StockMovement $movement) {}
}
