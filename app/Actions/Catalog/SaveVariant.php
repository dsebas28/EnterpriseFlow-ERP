<?php

namespace App\Actions\Catalog;

use App\DTOs\ProductData;
use App\Enums\ProductType;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Product;

/**
 * Creates or updates a variant of a variable product. Category and tax
 * rate are inherited from the parent; the name is derived from it.
 */
final class SaveVariant
{
    public function handle(Product $parent, ?Product $variant, ProductData $data): Product
    {
        if ($parent->type !== ProductType::Variable) {
            throw new BusinessRuleViolation('Only products with variants can have variants.');
        }

        if ($variant !== null && $variant->parent_id !== $parent->id) {
            throw new BusinessRuleViolation('This variant belongs to another product.');
        }

        // Keep the order the user entered (it drives the name); compare
        // combinations order-insensitively ("M / Red" == "Red / M").
        $attributes = $data->variantAttributes ?? [];
        $signature = self::signature($attributes);

        $duplicate = $parent->variants()
            ->when($variant, fn ($query) => $query->whereKeyNot($variant->id))
            ->get(['id', 'variant_attributes'])
            ->contains(fn (Product $sibling): bool => self::signature($sibling->variant_attributes ?? []) === $signature);

        if ($duplicate) {
            throw new BusinessRuleViolation('A variant with the same attributes already exists.');
        }

        $variant ??= new Product;
        $variant->fill([
            ...$data->toAttributes(),
            'name' => $parent->name.' — '.implode(' / ', $attributes),
            'description' => $parent->description,
            'category_id' => $parent->category_id,
            'tax_rate' => $parent->tax_rate,
            'variant_attributes' => $attributes,
        ]);
        $variant->type = ProductType::Variant;
        $variant->parent_id = $parent->id;
        $variant->save();

        return $variant;
    }

    /**
     * @param  array<string, string>  $attributes
     */
    private static function signature(array $attributes): string
    {
        $normalised = array_change_key_case(array_map('mb_strtolower', $attributes));
        ksort($normalised);

        return (string) json_encode($normalised);
    }
}
