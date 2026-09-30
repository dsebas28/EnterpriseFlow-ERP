<?php

namespace App\Actions\Purchasing;

use App\DTOs\PurchaseOrderData;
use App\Enums\DocumentType;
use App\Enums\PurchaseOrderStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\Documents\DocumentNumberGenerator;
use App\Support\Documents\LineCalculator;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Creates a draft purchase order or rewrites the lines of an existing draft.
 * Lines snapshot the product description, cost and tax at save time.
 */
final class SavePurchaseOrder
{
    public function __construct(
        private readonly DocumentNumberGenerator $numbers,
        private readonly TenantContext $tenant,
    ) {}

    public function handle(?PurchaseOrder $order, PurchaseOrderData $data, User $user): PurchaseOrder
    {
        if ($order !== null && ! $order->status->isEditable()) {
            throw new BusinessRuleViolation("Purchase order {$order->number} can no longer be edited ({$order->status->label()}).");
        }

        return DB::transaction(function () use ($order, $data, $user): PurchaseOrder {
            if ($order === null) {
                $order = new PurchaseOrder;
                $order->forceFill([
                    'number' => $this->numbers->next(DocumentType::PurchaseOrder),
                    'status' => PurchaseOrderStatus::Draft,
                    'currency' => $this->tenant->companyOrFail()->currency,
                    'created_by' => $user->id,
                ]);
            } else {
                // Re-read under lock: a concurrent submit must not slip in.
                $order = PurchaseOrder::lockForUpdate()->findOrFail($order->id);
                if (! $order->status->isEditable()) {
                    throw new BusinessRuleViolation("Purchase order {$order->number} can no longer be edited.");
                }
            }

            $products = Product::whereKey(array_column($data->lines, 'product_id'))->get()->keyBy('id');

            $lines = [];
            foreach ($data->lines as $position => $line) {
                /** @var Product $product */
                $product = $products->get($line['product_id']);
                $amounts = LineCalculator::line($line['quantity'], $line['unit_cost'], $line['tax_rate'], currency: $order->currency);

                $lines[] = [
                    'product_id' => $product->id,
                    'description' => $product->name,
                    'quantity' => $line['quantity'],
                    'unit_cost' => $line['unit_cost'],
                    'tax_rate' => $line['tax_rate'],
                    'line_subtotal' => $amounts->subtotal,
                    'line_tax' => $amounts->tax,
                    'line_total' => $amounts->total,
                    'position' => $position,
                    'amounts' => $amounts,
                ];
            }

            $totals = LineCalculator::totals(array_column($lines, 'amounts'));

            $order->forceFill([
                'supplier_id' => $data->supplierId,
                'warehouse_id' => $data->warehouseId,
                'order_date' => $data->orderDate,
                'expected_date' => $data->expectedDate,
                'notes' => $data->notes,
                'subtotal' => $totals['subtotal'],
                'tax_total' => $totals['tax'],
                'total' => $totals['total'],
            ])->save();

            // Drafts have no receipts, so their lines can simply be replaced.
            $order->items()->delete();
            foreach ($lines as $line) {
                unset($line['amounts']);
                $order->items()->make()->forceFill($line)->save();
            }

            return $order->refresh();
        });
    }
}
