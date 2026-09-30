<?php

namespace App\Http\Resources;

use App\Models\SupplierBill;
use App\Models\SupplierBillItem;
use App\Support\Money\BasisPoints;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SupplierBill
 */
class SupplierBillResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'supplier_reference' => $this->supplier_reference,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'supplier' => $this->whenLoaded('supplier', fn () => ['id' => $this->supplier->id, 'name' => $this->supplier->name]),
            'purchase_order' => $this->whenLoaded('purchaseOrder', fn () => ['id' => $this->purchaseOrder->id, 'number' => $this->purchaseOrder->number]),
            'bill_date' => $this->bill_date->toDateString(),
            'due_date' => $this->due_date->toDateString(),
            'currency' => $this->currency,
            'subtotal' => MoneyResource::make($this->money($this->subtotal)),
            'tax_total' => MoneyResource::make($this->money($this->tax_total)),
            'total' => MoneyResource::make($this->money($this->total)),
            'amount_paid' => MoneyResource::make($this->money($this->amount_paid)),
            'balance_due' => MoneyResource::make($this->money($this->balanceDue())),
            'notes' => $this->notes,
            'created_by' => $this->whenLoaded('creator', fn () => $this->creator?->name),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancel_reason' => $this->cancel_reason,
            'created_at' => $this->created_at?->toIso8601String(),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn (SupplierBillItem $item) => [
                'id' => $item->id,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_cost' => MoneyResource::make($this->money($item->unit_cost)),
                'tax_rate' => BasisPoints::toPercent($item->tax_rate),
                'line_total' => MoneyResource::make($this->money($item->line_total)),
            ])),
        ];
    }
}
