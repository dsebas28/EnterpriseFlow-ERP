<?php

namespace App\Http\Requests\Inventory;

use App\Enums\ProductType;
use App\Models\Product;
use App\Models\Warehouse;
use App\Support\Tenancy\TenantRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Exists;

/**
 * Shared helpers: ids must point to a live stockable product and an active
 * warehouse of the active company.
 */
abstract class InventoryRequest extends FormRequest
{
    protected function stockableProductRule(): Exists
    {
        return TenantRule::exists('products')
            ->whereNull('deleted_at')
            ->whereNot('type', ProductType::Variable->value);
    }

    protected function activeWarehouseRule(): Exists
    {
        return TenantRule::exists('warehouses')
            ->whereNull('deleted_at')
            ->where('is_active', true);
    }

    public function product(): Product
    {
        return Product::findOrFail($this->validated('product_id'));
    }

    public function warehouse(string $key = 'warehouse_id'): Warehouse
    {
        return Warehouse::findOrFail($this->validated($key));
    }
}
