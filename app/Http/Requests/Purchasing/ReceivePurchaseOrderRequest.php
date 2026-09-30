<?php

namespace App\Http\Requests\Purchasing;

use App\Models\PurchaseOrder;
use App\Models\Warehouse;
use App\Support\Tenancy\TenantRule;
use Illuminate\Foundation\Http\FormRequest;

class ReceivePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $order = $this->route('purchase_order');

        return $order instanceof PurchaseOrder && ($this->user()?->can('receive', $order) ?? false);
    }

    /**
     * Quantities are checked against what is still pending inside the
     * action, under a row lock; here we only validate the shape.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'warehouse_id' => ['nullable', 'string', TenantRule::exists('warehouses')->whereNull('deleted_at')->where('is_active', true)],
            'notes' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', 'integer'],
            'lines.*.quantity' => ['required', 'integer', 'min:0', 'max:1000000'],
        ];
    }

    /**
     * @return array<int, int> purchase_order_item_id => quantity
     */
    public function quantities(): array
    {
        $quantities = [];

        foreach ($this->validated('lines') as $line) {
            $quantities[(int) $line['item_id']] = (int) $line['quantity'];
        }

        return $quantities;
    }

    public function warehouse(): ?Warehouse
    {
        $id = $this->validated('warehouse_id');

        return $id ? Warehouse::findOrFail($id) : null;
    }
}
