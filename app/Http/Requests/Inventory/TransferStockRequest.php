<?php

namespace App\Http\Requests\Inventory;

class TransferStockRequest extends InventoryRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('inventory.transfer') ?? false;
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'string', $this->stockableProductRule()],
            'from_warehouse_id' => ['required', 'string', $this->activeWarehouseRule()],
            'to_warehouse_id' => ['required', 'string', 'different:from_warehouse_id', $this->activeWarehouseRule()],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
