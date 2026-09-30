<?php

namespace App\Http\Requests\Purchasing;

use App\DTOs\PurchaseOrderData;
use App\Enums\PartyStatus;
use App\Enums\ProductType;
use App\Models\PurchaseOrder;
use App\Support\Money\Currency;
use App\Support\Tenancy\TenantContext;
use App\Support\Tenancy\TenantRule;
use Illuminate\Foundation\Http\FormRequest;

class SavePurchaseOrderRequest extends FormRequest
{
    public const MAX_LINES = 200;

    public function authorize(): bool
    {
        $order = $this->route('purchase_order');

        return $order instanceof PurchaseOrder
            ? $this->user()?->can('update', $order) ?? false
            : $this->user()?->can('create', PurchaseOrder::class) ?? false;
    }

    /**
     * Totals are intentionally absent: they are computed server-side, and
     * anything the client sends for them is ignored.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        $decimals = Currency::minorUnits($this->currency());

        return [
            'supplier_id' => ['required', 'string', TenantRule::exists('suppliers')->whereNull('deleted_at')->where('status', PartyStatus::Active->value)],
            'warehouse_id' => ['required', 'string', TenantRule::exists('warehouses')->whereNull('deleted_at')->where('is_active', true)],
            'order_date' => ['required', 'date'],
            'expected_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:'.self::MAX_LINES],
            'lines.*.product_id' => [
                'required', 'string', 'distinct',
                TenantRule::exists('products')->whereNull('deleted_at')->whereNot('type', ProductType::Variable->value),
            ],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'lines.*.unit_cost' => ['required', 'decimal:0,'.$decimals, 'min:0', 'max:9999999999'],
            'lines.*.tax_rate' => ['required', 'decimal:0,2', 'min:0', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'lines.required' => 'Add at least one product line.',
            'lines.*.product_id.distinct' => 'Each product can only appear once; adjust the quantity instead.',
        ];
    }

    public function toData(): PurchaseOrderData
    {
        return PurchaseOrderData::fromArray($this->validated(), $this->currency());
    }

    private function currency(): string
    {
        return app(TenantContext::class)->companyOrFail()->currency;
    }
}
