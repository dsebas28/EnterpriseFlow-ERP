<?php

namespace App\Http\Requests\Inventory;

use App\Enums\StockMovementType;
use App\Support\Money\Currency;
use App\Support\Money\Money;
use App\Support\Tenancy\TenantContext;
use Illuminate\Validation\Rule;

class ManualMovementRequest extends InventoryRequest
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
            'type' => ['required', Rule::in([StockMovementType::ManualIn->value, StockMovementType::ManualOut->value])],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'unit_cost' => ['nullable', 'decimal:0,'.Currency::minorUnits($this->currency()), 'min:0'],
            'notes' => ['required', 'string', 'max:255'],
        ];
    }

    public function type(): StockMovementType
    {
        return StockMovementType::from($this->validated('type'));
    }

    public function unitCost(): ?int
    {
        $cost = $this->validated('unit_cost');

        return $cost === null ? null : Money::fromDecimal((string) $cost, $this->currency())->minor;
    }

    private function currency(): string
    {
        return app(TenantContext::class)->companyOrFail()->currency;
    }
}
