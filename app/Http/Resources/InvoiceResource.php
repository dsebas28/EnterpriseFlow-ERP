<?php

namespace App\Http\Resources;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Support\Money\BasisPoints;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Invoice
 */
class InvoiceResource extends JsonResource
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
            'sale' => $this->whenLoaded('sale', fn () => ['id' => $this->sale->id, 'number' => $this->sale->number]),
            'customer' => $this->whenLoaded('customer', fn () => ['id' => $this->customer->id, 'name' => $this->customer->name]),
            'issue_date' => $this->issue_date?->toDateString(),
            'due_date' => $this->due_date->toDateString(),
            'currency' => $this->currency,
            'discount_total' => MoneyResource::make($this->money($this->discount_total)),
            'subtotal' => MoneyResource::make($this->money($this->subtotal)),
            'tax_total' => MoneyResource::make($this->money($this->tax_total)),
            'total' => MoneyResource::make($this->money($this->total)),
            'amount_paid' => MoneyResource::make($this->money($this->amount_paid)),
            'balance_due' => MoneyResource::make($this->money($this->balanceDue())),
            'notes' => $this->notes,
            'pdf_status' => $this->pdf_status?->value,
            'pdf_generated_at' => $this->pdf_generated_at?->toIso8601String(),
            'issued_by' => $this->whenLoaded('issuer', fn () => $this->issuer?->name),
            'issued_at' => $this->issued_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancel_reason' => $this->cancel_reason,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn (InvoiceItem $item) => [
                'id' => $item->id,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_price' => MoneyResource::make($this->money($item->unit_price)),
                'discount_rate' => BasisPoints::toPercent($item->discount_rate),
                'tax_rate' => BasisPoints::toPercent($item->tax_rate),
                'line_total' => MoneyResource::make($this->money($item->line_total)),
            ])),
        ];
    }
}
