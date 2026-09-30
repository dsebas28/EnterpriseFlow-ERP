<?php

namespace App\Actions\Sales;

use App\DTOs\SaleData;
use App\Enums\DocumentType;
use App\Enums\SaleStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\Documents\DocumentNumberGenerator;
use App\Support\Documents\LineCalculator;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Creates a draft sale or rewrites the lines of an existing draft. Stock is
 * not touched until the sale is confirmed.
 */
final class SaveSale
{
    public function __construct(
        private readonly DocumentNumberGenerator $numbers,
        private readonly TenantContext $tenant,
    ) {}

    public function handle(?Sale $sale, SaleData $data, User $user): Sale
    {
        if ($sale !== null && ! $sale->status->isEditable()) {
            throw new BusinessRuleViolation("Sale {$sale->number} can no longer be edited ({$sale->status->label()}).");
        }

        return DB::transaction(function () use ($sale, $data, $user): Sale {
            if ($sale === null) {
                $sale = new Sale;
                $sale->forceFill([
                    'number' => $this->numbers->next(DocumentType::Sale),
                    'status' => SaleStatus::Draft,
                    'currency' => $this->tenant->companyOrFail()->currency,
                    'created_by' => $user->id,
                ]);
            } else {
                $sale = Sale::lockForUpdate()->findOrFail($sale->id);
                if (! $sale->status->isEditable()) {
                    throw new BusinessRuleViolation("Sale {$sale->number} can no longer be edited.");
                }
            }

            $products = Product::whereKey(array_column($data->lines, 'product_id'))->get()->keyBy('id');

            $lines = [];
            foreach ($data->lines as $position => $line) {
                /** @var Product $product */
                $product = $products->get($line['product_id']);
                $amounts = LineCalculator::line(
                    $line['quantity'], $line['unit_price'], $line['tax_rate'], $line['discount_rate'], $sale->currency,
                );

                $lines[] = [
                    'row' => [
                        'product_id' => $product->id,
                        'description' => $product->name,
                        'quantity' => $line['quantity'],
                        'unit_price' => $line['unit_price'],
                        'discount_rate' => $line['discount_rate'],
                        'tax_rate' => $line['tax_rate'],
                        'line_discount' => $amounts->discount,
                        'line_subtotal' => $amounts->subtotal,
                        'line_tax' => $amounts->tax,
                        'line_total' => $amounts->total,
                        'position' => $position,
                    ],
                    'amounts' => $amounts,
                ];
            }

            $totals = LineCalculator::totals(array_column($lines, 'amounts'));

            $sale->forceFill([
                'customer_id' => $data->customerId,
                'warehouse_id' => $data->warehouseId,
                'sale_date' => $data->saleDate,
                'notes' => $data->notes,
                'discount_total' => $totals['discount'],
                'subtotal' => $totals['subtotal'],
                'tax_total' => $totals['tax'],
                'total' => $totals['total'],
            ])->save();

            $sale->items()->delete();
            foreach ($lines as $line) {
                $sale->items()->make()->forceFill($line['row'])->save();
            }

            return $sale->refresh();
        });
    }
}
