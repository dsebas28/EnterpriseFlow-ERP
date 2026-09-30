<?php

namespace App\Http\Resources;

use App\Models\StockMovement;
use App\Support\Money\Money;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StockMovement
 */
class StockMovementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currency = app(TenantContext::class)->companyOrFail()->currency;

        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'quantity' => $this->quantity,
            'balance_after' => $this->balance_after,
            'unit_cost' => $this->unit_cost === null ? null : MoneyResource::make(new Money($this->unit_cost, $currency)),
            'product' => ['id' => $this->product->id, 'sku' => $this->product->sku, 'name' => $this->product->name],
            'warehouse' => ['id' => $this->warehouse->id, 'code' => $this->warehouse->code, 'name' => $this->warehouse->name],
            'user' => $this->user?->name,
            'reference' => $this->reference_type ? ['type' => $this->reference_type, 'id' => $this->reference_id] : null,
            'transfer_id' => $this->transfer_id,
            'notes' => $this->notes,
            'occurred_at' => $this->occurred_at->toIso8601String(),
        ];
    }
}
