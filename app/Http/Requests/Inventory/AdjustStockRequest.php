<?php

namespace App\Http\Requests\Inventory;

class AdjustStockRequest extends InventoryRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('inventory.adjust') ?? false;
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'string', $this->stockableProductRule()],
            'warehouse_id' => ['required', 'string', $this->activeWarehouseRule()],
            'counted_quantity' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}
