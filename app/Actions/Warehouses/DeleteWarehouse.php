<?php

namespace App\Actions\Warehouses;

use App\Exceptions\BusinessRuleViolation;
use App\Models\Warehouse;

final class DeleteWarehouse
{
    public function handle(Warehouse $warehouse): void
    {
        if ($warehouse->is_default) {
            throw new BusinessRuleViolation('The default warehouse cannot be deleted. Choose another default first.');
        }

        // Stock-related guards are added together with inventory movements.
        $warehouse->delete();
    }
}
