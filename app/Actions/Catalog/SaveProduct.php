<?php

namespace App\Actions\Catalog;

use App\DTOs\ProductData;
use App\Enums\ProductType;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * Creates or updates a simple or variable product.
 *
 * The product type is fixed at creation: switching between simple and
 * variable would orphan stock or variants.
 */
final class SaveProduct
{
    public function handle(?Product $product, ProductData $data): Product
    {
        if ($product?->type === ProductType::Variant) {
            throw new BusinessRuleViolation('Variants are edited from their parent product.');
        }

        if ($product === null && $data->type === ProductType::Variant) {
            throw new BusinessRuleViolation('Variants are created from their parent product.');
        }

        return DB::transaction(function () use ($product, $data): Product {
            $isNew = $product === null;
            $product ??= new Product;

            $product->fill([...$data->toAttributes(), 'variant_attributes' => null]);

            if ($isNew) {
                $product->type = $data->type;
            }

            $product->save();

            if (! $isNew && $product->type === ProductType::Variable) {
                // Category and tax are shared by all variants of a product.
                $product->variants()->update([
                    'category_id' => $product->category_id,
                    'tax_rate' => $product->tax_rate,
                ]);
            }

            return $product;
        });
    }
}
