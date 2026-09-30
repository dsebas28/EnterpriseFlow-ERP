<?php

namespace App\Actions\Invoicing;

use App\Enums\InvoiceStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Invoice;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Drafts the invoice of a confirmed sale, copying its lines and totals as
 * a snapshot. A sale can have only one invoice that is not cancelled
 * (checked here and enforced by a partial unique index).
 */
final class CreateInvoiceFromSale
{
    public function handle(Sale $sale, string $dueDate, ?string $notes, User $user): Invoice
    {
        return DB::transaction(function () use ($sale, $dueDate, $notes, $user): Invoice {
            $sale = Sale::lockForUpdate()->findOrFail($sale->id);

            if (! $sale->status->hasDeductedStock()) {
                throw new BusinessRuleViolation("Only confirmed sales can be invoiced ({$sale->number} is {$sale->status->label()}).");
            }

            $alreadyInvoiced = Invoice::where('sale_id', $sale->id)
                ->where('status', '!=', InvoiceStatus::Cancelled->value)
                ->exists();

            if ($alreadyInvoiced) {
                throw new BusinessRuleViolation("Sale {$sale->number} already has an invoice.");
            }

            $invoice = new Invoice;
            $invoice->forceFill([
                'sale_id' => $sale->id,
                'customer_id' => $sale->customer_id,
                'status' => InvoiceStatus::Draft,
                'due_date' => $dueDate,
                'currency' => $sale->currency,
                'discount_total' => $sale->discount_total,
                'subtotal' => $sale->subtotal,
                'tax_total' => $sale->tax_total,
                'total' => $sale->total,
                'amount_paid' => 0,
                'notes' => $notes,
                'created_by' => $user->id,
            ])->save();

            $sale->items()->get()->each(function (SaleItem $item) use ($invoice): void {
                $invoice->items()->make()->forceFill([
                    'product_id' => $item->product_id,
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'discount_rate' => $item->discount_rate,
                    'tax_rate' => $item->tax_rate,
                    'line_discount' => $item->line_discount,
                    'line_subtotal' => $item->line_subtotal,
                    'line_tax' => $item->line_tax,
                    'line_total' => $item->line_total,
                    'position' => $item->position,
                ])->save();
            });

            return $invoice;
        });
    }
}
