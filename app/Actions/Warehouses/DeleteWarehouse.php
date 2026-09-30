<?php

namespace App\Actions\Warehouses;

use App\Exceptions\BusinessRuleViolation;
use App\Models\StockLevel;
use App\Models\Warehouse;

final class DeleteWarehouse
{
    public function handle(Warehouse $warehouse): void
    {
        if ($warehouse->is_default) {
            throw new BusinessRuleViolation('The default warehouse cannot be deleted. Choose another default first.');
        }

        $hasStock = StockLevel::query()
            ->where('warehouse_id', $warehouse->id)
            ->where('quantity', '!=', 0)
            ->exists();

        if ($hasStock) {
            throw new BusinessRuleViolation('This warehouse still holds stock. Transfer or adjust it before deleting the warehouse.');
        }

        $warehouse->delete();
    }
}
