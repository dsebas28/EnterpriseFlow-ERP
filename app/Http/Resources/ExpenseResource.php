<?php

namespace App\Http\Resources;

use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Expense
 */
class ExpenseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'number' => $this->number,
            'category' => $this->whenLoaded('category', fn () => ['id' => $this->category->id, 'name' => $this->category->name]),
            'supplier' => $this->whenLoaded('supplier', fn () => $this->supplier ? ['id' => $this->supplier->id, 'name' => $this->supplier->name] : null),
            'description' => $this->description,
            'amount' => MoneyResource::make($this->money()),
            'expense_date' => $this->expense_date->toDateString(),
            'payment_method' => $this->payment_method?->value,
            'payment_method_label' => $this->payment_method?->label(),
            'has_receipt' => $this->receipt_path !== null,
            'receipt_name' => $this->receipt_name,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'created_by' => $this->whenLoaded('creator', fn () => $this->creator?->name),
            'reviewed_by' => $this->whenLoaded('reviewer', fn () => $this->reviewer?->name),
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'rejection_reason' => $this->rejection_reason,
            'can' => [
                'update' => $this->isPending() && ($user?->can('update', $this->resource) ?? false),
                'review' => $this->isPending() && $this->created_by !== $user?->id && ($user?->can('review', $this->resource) ?? false),
            ],
        ];
    }
}
