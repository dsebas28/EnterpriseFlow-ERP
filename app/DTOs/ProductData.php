<?php

namespace App\DTOs;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Support\Money\BasisPoints;
use App\Support\Money\Money;
use Illuminate\Support\Str;

/**
 * Validated product input with money already converted to minor units.
 */
final readonly class ProductData
{
    /**
     * @param  array<string, string>|null  $variantAttributes
     */
    public function __construct(
        public string $name,
        public string $sku,
        public int $cost,
        public int $price,
        public int $taxRate,
        public int $minStock,
        public ProductStatus $status,
        public ProductType $type = ProductType::Simple,
        public ?string $barcode = null,
        public ?string $description = null,
        public ?int $categoryId = null,
        public ?array $variantAttributes = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data  Output of a FormRequest's validated()
     */
    public static function fromArray(array $data, string $currency, ?ProductType $type = null): self
    {
        return new self(
            // Variants have no name of their own; SaveVariant derives it from the parent.
            name: trim((string) ($data['name'] ?? '')),
            sku: Str::upper(trim($data['sku'])),
            cost: Money::fromDecimal((string) $data['cost'], $currency)->minor,
            price: Money::fromDecimal((string) $data['price'], $currency)->minor,
            taxRate: BasisPoints::fromPercent((string) ($data['tax_rate'] ?? '0')),
            minStock: (int) ($data['min_stock'] ?? 0),
            status: ProductStatus::from($data['status'] ?? ProductStatus::Active->value),
            type: $type ?? ProductType::from($data['type'] ?? ProductType::Simple->value),
            barcode: isset($data['barcode']) && $data['barcode'] !== '' ? trim($data['barcode']) : null,
            description: $data['description'] ?? null,
            categoryId: isset($data['category_id']) ? (int) $data['category_id'] : null,
            variantAttributes: isset($data['attributes']) ? self::normaliseAttributes($data['attributes']) : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'name' => $this->name,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'description' => $this->description,
            'category_id' => $this->categoryId,
            'cost' => $this->cost,
            'price' => $this->price,
            'tax_rate' => $this->taxRate,
            'min_stock' => $this->minStock,
            'status' => $this->status,
            'variant_attributes' => $this->variantAttributes,
        ];
    }

    /**
     * @param  array<int|string, mixed>  $attributes  list of {name, value} pairs
     * @return array<string, string>
     */
    private static function normaliseAttributes(array $attributes): array
    {
        $normalised = [];

        foreach ($attributes as $attribute) {
            $normalised[Str::lower(trim($attribute['name']))] = trim($attribute['value']);
        }

        return $normalised;
    }
}
