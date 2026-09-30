<?php

namespace App\Http\Resources;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Support\Money\BasisPoints;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PurchaseOrder
 */
class PurchaseOrderResource extends JsonResource
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
            'supplier' => $this->whenLoaded('supplier', fn () => ['id' => $this->supplier->id, 'name' => $this->supplier->name]),
            'warehouse' => $this->whenLoaded('warehouse', fn () => ['id' => $this->warehouse->id, 'name' => $this->warehouse->name]),
            'order_date' => $this->order_date->toDateString(),
            'expected_date' => $this->expected_date?->toDateString(),
            'currency' => $this->currency,
            'subtotal' => MoneyResource::make($this->money($this->subtotal)),
            'tax_total' => MoneyResource::make($this->money($this->tax_total)),
            'total' => MoneyResource::make($this->money($this->total)),
            'notes' => $this->notes,
            'created_by' => $this->whenLoaded('creator', fn () => $this->creator?->name),
            'approved_by' => $this->whenLoaded('approver', fn () => $this->approver?->name),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancel_reason' => $this->cancel_reason,
            'received_at' => $this->received_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn (PurchaseOrderItem $item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'sku' => $item->product->sku,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'received_quantity' => $item->received_quantity,
                'remaining_quantity' => $item->remainingQuantity(),
                'unit_cost' => MoneyResource::make($this->money($item->unit_cost)),
                'tax_rate' => BasisPoints::toPercent($item->tax_rate),
                'line_subtotal' => MoneyResource::make($this->money($item->line_subtotal)),
                'line_tax' => MoneyResource::make($this->money($item->line_tax)),
                'line_total' => MoneyResource::make($this->money($item->line_total)),
            ])),
            'receipts' => $this->whenLoaded('receipts', fn () => $this->receipts->map(fn (PurchaseReceipt $receipt) => [
                'id' => $receipt->id,
                'number' => $receipt->number,
                'warehouse' => $receipt->warehouse->name,
                'received_by' => $receipt->receiver?->name,
                'received_at' => $receipt->received_at->toIso8601String(),
                'units' => (int) $receipt->items->sum('quantity'),
                'notes' => $receipt->notes,
            ])),
        ];
    }
}
