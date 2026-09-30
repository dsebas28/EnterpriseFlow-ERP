<?php

namespace App\Http\Resources;

use App\Models\Sale;
use App\Models\SaleItem;
use App\Support\Money\BasisPoints;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Sale
 */
class SaleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'customer' => $this->whenLoaded('customer', fn () => ['id' => $this->customer->id, 'name' => $this->customer->name]),
            'warehouse' => $this->whenLoaded('warehouse', fn () => ['id' => $this->warehouse->id, 'name' => $this->warehouse->name]),
            'sale_date' => $this->sale_date->toDateString(),
            'currency' => $this->currency,
            'discount_total' => MoneyResource::make($this->money($this->discount_total)),
            'subtotal' => MoneyResource::make($this->money($this->subtotal)),
            'tax_total' => MoneyResource::make($this->money($this->tax_total)),
            'total' => MoneyResource::make($this->money($this->total)),
            'amount_paid' => MoneyResource::make($this->money($this->amount_paid)),
            'balance_due' => MoneyResource::make($this->money($this->balanceDue())),
            'notes' => $this->notes,
            'created_by' => $this->whenLoaded('creator', fn () => $this->creator?->name),
            'confirmed_by' => $this->whenLoaded('confirmer', fn () => $this->confirmer?->name),
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancel_reason' => $this->cancel_reason,
            'created_at' => $this->created_at?->toIso8601String(),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn (SaleItem $item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'sku' => $item->product->sku,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_price' => MoneyResource::make($this->money($item->unit_price)),
                'discount_rate' => BasisPoints::toPercent($item->discount_rate),
                'tax_rate' => BasisPoints::toPercent($item->tax_rate),
                'line_discount' => MoneyResource::make($this->money($item->line_discount)),
                'line_subtotal' => MoneyResource::make($this->money($item->line_subtotal)),
                'line_tax' => MoneyResource::make($this->money($item->line_tax)),
                'line_total' => MoneyResource::make($this->money($item->line_total)),
            ])),
        ];
    }
}
