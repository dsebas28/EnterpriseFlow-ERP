<?php

namespace App\Http\Resources;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Payment
 */
class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'direction' => $this->direction->value,
            'direction_label' => $this->direction->label(),
            'method' => $this->method->value,
            'method_label' => $this->method->label(),
            'amount' => MoneyResource::make($this->money()),
            'paid_at' => $this->paid_at->toDateString(),
            'reference' => $this->reference,
            'notes' => $this->notes,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'document' => $this->when(
                $this->relationLoaded('invoice') || $this->relationLoaded('supplierBill'),
                fn () => $this->invoice_id !== null
                    ? ['type' => 'invoice', 'id' => $this->invoice_id, 'number' => $this->invoice?->number, 'party' => $this->invoice?->customer->name]
                    : ['type' => 'supplier_bill', 'id' => $this->supplier_bill_id, 'number' => $this->supplierBill?->number, 'party' => $this->supplierBill?->supplier->name],
            ),
            'created_by' => $this->whenLoaded('creator', fn () => $this->creator?->name),
            'voided_by' => $this->whenLoaded('voider', fn () => $this->voider?->name),
            'voided_at' => $this->voided_at?->toIso8601String(),
            'void_reason' => $this->void_reason,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
