<?php

namespace App\Actions\Catalog;

use App\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * Soft-deletes a product (and its variants). Historical documents keep
 * pointing at it; the SKU stays reserved so it is never reused by mistake.
 */
final class DeleteProduct
{
    public function handle(Product $product): void
    {
        // Stock-related guards are added together with inventory movements.
        DB::transaction(function () use ($product): void {
            $product->variants()->get()->each->delete();
            $product->delete();
        });
    }
}
