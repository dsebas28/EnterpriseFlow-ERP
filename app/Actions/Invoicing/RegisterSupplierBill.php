<?php

namespace App\Actions\Invoicing;

use App\Enums\DocumentType;
use App\Enums\InvoiceStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\SupplierBill;
use App\Models\User;
use App\Services\Documents\DocumentNumberGenerator;
use App\Support\Documents\LineCalculator;
use Illuminate\Support\Facades\DB;

/**
 * Registers a supplier's invoice against a purchase order.
 *
 * A supplier can only bill what was actually received and not billed yet
 * (received − billed per line), and the same supplier reference cannot be
 * registered twice. Amounts use the order's agreed unit cost and tax.
 */
final class RegisterSupplierBill
{
    public function __construct(private readonly DocumentNumberGenerator $numbers) {}

    /**
     * @param  array<int, int>  $quantities  purchase_order_item_id => quantity billed
     */
    public function handle(
        PurchaseOrder $order,
        string $supplierReference,
        string $billDate,
        string $dueDate,
        array $quantities,
        User $user,
        ?string $notes = null,
    ): SupplierBill {
        $quantities = array_filter($quantities, fn (int $quantity) => $quantity > 0);

        if ($quantities === []) {
            throw new BusinessRuleViolation('Enter the billed quantity of at least one line.');
        }

        return DB::transaction(function () use ($order, $supplierReference, $billDate, $dueDate, $quantities, $user, $notes): SupplierBill {
            $order = PurchaseOrder::lockForUpdate()->findOrFail($order->id);
            $reference = trim($supplierReference);

            $duplicate = SupplierBill::where('supplier_id', $order->supplier_id)
                ->where('supplier_reference', $reference)
                ->where('status', '!=', InvoiceStatus::Cancelled->value)
                ->exists();

            if ($duplicate) {
                throw new BusinessRuleViolation("Supplier invoice {$reference} is already registered.");
            }

            $items = $order->items()->get()->keyBy('id');
            $lines = [];

            foreach ($quantities as $itemId => $quantity) {
                /** @var PurchaseOrderItem|null $item */
                $item = $items->get($itemId);

                if ($item === null) {
                    throw new BusinessRuleViolation('A billed line does not belong to this purchase order.');
                }

                if ($quantity > $item->billableQuantity()) {
                    throw new BusinessRuleViolation(sprintf(
                        'Cannot bill %d of %s: only %d received and not yet billed.',
                        $quantity,
                        $item->description,
                        $item->billableQuantity(),
                    ));
                }

                $lines[] = [
                    'item' => $item,
                    'quantity' => $quantity,
                    'amounts' => LineCalculator::line($quantity, $item->unit_cost, $item->tax_rate, currency: $order->currency),
                ];
            }

            $totals = LineCalculator::totals(array_column($lines, 'amounts'));

            $bill = new SupplierBill;
            $bill->forceFill([
                'number' => $this->numbers->next(DocumentType::SupplierBill),
                'supplier_id' => $order->supplier_id,
                'purchase_order_id' => $order->id,
                'supplier_reference' => $reference,
                'status' => InvoiceStatus::Issued,
                'bill_date' => $billDate,
                'due_date' => $dueDate,
                'currency' => $order->currency,
                'subtotal' => $totals['subtotal'],
                'tax_total' => $totals['tax'],
                'total' => $totals['total'],
                'notes' => $notes,
                'created_by' => $user->id,
            ])->save();

            foreach ($lines as $line) {
                /** @var PurchaseOrderItem $item */
                $item = $line['item'];

                $bill->items()->make()->forceFill([
                    'purchase_order_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'description' => $item->description,
                    'quantity' => $line['quantity'],
                    'unit_cost' => $item->unit_cost,
                    'tax_rate' => $item->tax_rate,
                    'line_subtotal' => $line['amounts']->subtotal,
                    'line_tax' => $line['amounts']->tax,
                    'line_total' => $line['amounts']->total,
                ])->save();

                $item->forceFill(['billed_quantity' => $item->billed_quantity + $line['quantity']])->save();
            }

            return $bill;
        });
    }
}
