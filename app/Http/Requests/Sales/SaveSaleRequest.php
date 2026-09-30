<?php

namespace App\Http\Requests\Sales;

use App\DTOs\SaleData;
use App\Enums\PartyStatus;
use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Models\Sale;
use App\Support\Money\Currency;
use App\Support\Tenancy\TenantContext;
use App\Support\Tenancy\TenantRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveSaleRequest extends FormRequest
{
    public const MAX_LINES = 200;

    public function authorize(): bool
    {
        $sale = $this->route('sale');

        return $sale instanceof Sale
            ? $this->user()?->can('update', $sale) ?? false
            : $this->user()?->can('create', Sale::class) ?? false;
    }

    /**
     * Totals are intentionally absent: they are computed server-side.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        $decimals = Currency::minorUnits(app(TenantContext::class)->companyOrFail()->currency);

        return [
            'customer_id' => ['required', 'string', TenantRule::exists('customers')->whereNull('deleted_at')->where('status', PartyStatus::Active->value)],
            'warehouse_id' => ['required', 'string', TenantRule::exists('warehouses')->whereNull('deleted_at')->where('is_active', true)],
            'sale_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:'.self::MAX_LINES],
            'lines.*.product_id' => [
                'required', 'string', 'distinct',
                TenantRule::exists('products')
                    ->whereNull('deleted_at')
                    ->where('status', ProductStatus::Active->value)
                    ->whereNot('type', ProductType::Variable->value),
            ],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'lines.*.unit_price' => ['required', 'decimal:0,'.$decimals, 'min:0', 'max:9999999999'],
            'lines.*.discount_rate' => ['nullable', 'decimal:0,2', 'min:0', 'max:100'],
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

    public function toData(): SaleData
    {
        return SaleData::fromArray($this->validated(), app(TenantContext::class)->companyOrFail()->currency);
    }
}
