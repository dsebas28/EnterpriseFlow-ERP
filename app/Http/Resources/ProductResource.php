<?php

namespace App\Http\Resources;

use App\Models\Product;
use App\Models\ProductImage;
use App\Support\Money\BasisPoints;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'parent_id' => $this->parent_id,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status->value,
            'category' => $this->whenLoaded('category', fn () => $this->category
                ? ['id' => $this->category->id, 'name' => $this->category->name]
                : null),
            'cost' => MoneyResource::make($this->costMoney()),
            'price' => MoneyResource::make($this->priceMoney()),
            'tax_rate' => BasisPoints::toPercent($this->tax_rate),
            'min_stock' => $this->min_stock,
            'attributes' => $this->variant_attributes,
            'variants_count' => $this->whenCounted('variants'),
            'variants' => self::collection($this->whenLoaded('variants')),
            'images' => $this->whenLoaded('images', fn () => $this->images->map(fn (ProductImage $image) => [
                'id' => $image->id,
                'url' => $image->url(),
            ])),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
