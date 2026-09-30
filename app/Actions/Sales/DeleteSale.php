<?php

namespace App\Actions\Sales;

use App\Exceptions\BusinessRuleViolation;
use App\Models\Sale;

final class DeleteSale
{
    public function handle(Sale $sale): void
    {
        if (! $sale->status->isEditable()) {
            throw new BusinessRuleViolation('Only draft sales can be deleted. Cancel it instead.');
        }

        $sale->delete();
    }
}
