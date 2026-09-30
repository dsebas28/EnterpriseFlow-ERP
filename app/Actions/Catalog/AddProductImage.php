<?php

namespace App\Actions\Catalog;

use App\Exceptions\BusinessRuleViolation;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\UploadedFile;

final class AddProductImage
{
    public const DISK = 'public';

    public function handle(Product $product, UploadedFile $file): ProductImage
    {
        if ($product->images()->count() >= Product::MAX_IMAGES) {
            throw new BusinessRuleViolation('A product can have at most '.Product::MAX_IMAGES.' images.');
        }

        // Random file name (store() uses a hash), namespaced by company so a
        // tenant's files can be listed, backed up or purged together.
        $path = $file->store("companies/{$product->company_id}/products/{$product->id}", self::DISK);

        if ($path === false) {
            throw new BusinessRuleViolation('The image could not be stored. Please try again.');
        }

        return $product->images()->create([
            'disk' => self::DISK,
            'path' => $path,
            'position' => (int) $product->images()->max('position') + 1,
        ]);
    }
}
