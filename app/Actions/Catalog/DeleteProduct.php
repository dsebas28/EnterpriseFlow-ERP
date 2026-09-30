<?php

namespace App\Actions\Catalog;

use App\Exceptions\BusinessRuleViolation;
use App\Models\Product;
use App\Models\StockLevel;
use Illuminate\Support\Facades\DB;

/**
 * Soft-deletes a product (and its variants). Historical documents keep
 * pointing at it; the SKU stays reserved so it is never reused by mistake.
 */
final class DeleteProduct
{
    public function handle(Product $product): void
    {
        DB::transaction(function () use ($product): void {
            $ids = [$product->id, ...$product->variants()->pluck('id')->all()];

            // Deleting an item that still has units on the shelf would make
            // that stock invisible; it must be sold, moved or adjusted first.
            $hasStock = StockLevel::query()
                ->whereIn('product_id', $ids)
                ->where('quantity', '!=', 0)
                ->lockForUpdate()
                ->exists();

            if ($hasStock) {
                throw new BusinessRuleViolation('This product still has stock. Adjust it to zero before deleting the product.');
            }

            $product->variants()->get()->each->delete();
            $product->delete();
        });
    }
}
