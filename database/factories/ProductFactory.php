<?php

namespace Database\Factories;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Models\Company;
use App\Models\Product;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        $cost = fake()->numberBetween(1_000, 200_000);

        return [
            'company_id' => fn () => app(TenantContext::class)->id() ?? Company::factory(),
            'sku' => strtoupper(fake()->unique()->bothify('SKU-####-??')),
            'barcode' => fake()->unique()->ean13(),
            'name' => ucfirst(fake()->words(3, true)),
            'description' => fake()->sentence(12),
            'cost' => $cost,
            // Margin between 20% and 80% over cost.
            'price' => (int) round($cost * fake()->randomFloat(2, 1.2, 1.8)),
            'tax_rate' => fake()->randomElement([0, 500, 1900]),
            'min_stock' => fake()->numberBetween(0, 20),
            'status' => ProductStatus::Active,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['status' => ProductStatus::Inactive]);
    }

    public function variable(): static
    {
        return $this->afterMaking(fn (Product $product) => $product->type = ProductType::Variable);
    }

    public function variantOf(Product $parent): static
    {
        return $this->state(['category_id' => $parent->category_id, 'tax_rate' => $parent->tax_rate])
            ->afterMaking(function (Product $product) use ($parent): void {
                $product->type = ProductType::Variant;
                $product->parent_id = $parent->id;
            });
    }
}
