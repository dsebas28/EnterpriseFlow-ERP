<?php

namespace App\Http\Requests\Catalog;

use App\Models\Warehouse;
use App\Support\Tenancy\TenantRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveWarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $warehouse = $this->route('warehouse');

        return $warehouse instanceof Warehouse
            ? $this->user()?->can('update', $warehouse) ?? false
            : $this->user()?->can('create', Warehouse::class) ?? false;
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        $warehouse = $this->route('warehouse');

        return [
            'code' => [
                'required', 'string', 'max:20', 'alpha_dash:ascii',
                TenantRule::unique('warehouses', 'code')->ignore($warehouse instanceof Warehouse ? $warehouse->id : null),
            ],
            'name' => ['required', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'is_active' => ['boolean'],
            'is_default' => ['boolean'],
        ];
    }
}
