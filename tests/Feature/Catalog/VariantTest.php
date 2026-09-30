<?php

use App\Enums\ProductType;
use App\Enums\SystemRole;
use App\Models\Category;
use App\Models\Product;

function variantPayload(array $overrides = []): array
{
    return [
        'sku' => 'TS-M-RED',
        'barcode' => null,
        'cost' => '20000',
        'price' => '45000',
        'min_stock' => 2,
        'status' => 'active',
        'attributes' => [
            ['name' => 'Size', 'value' => 'M'],
            ['name' => 'Color', 'value' => 'Red'],
        ],
        ...$overrides,
    ];
}

it('adds a variant that inherits category and tax from its parent', function () {
    [$user, $company] = memberWithRole(SystemRole::Manager);
    actAsCompany($company);
    $category = Category::factory()->create();
    $parent = Product::factory()->variable()->create(['name' => 'T-Shirt', 'category_id' => $category->id, 'tax_rate' => 1900]);

    $this->actingAs($user)
        ->post(route('catalog.products.variants.store', $parent), variantPayload())
        ->assertSessionHasNoErrors();

    actAsCompany($company);
    $variant = $parent->variants()->sole();

    expect($variant->type)->toBe(ProductType::Variant)
        ->and($variant->name)->toBe('T-Shirt — M / Red')
        ->and($variant->category_id)->toBe($category->id)
        ->and($variant->tax_rate)->toBe(1900)
        ->and($variant->variant_attributes)->toBe(['size' => 'M', 'color' => 'Red'])
        ->and($variant->price)->toBe(4500000);
});

it('rejects duplicate attribute combinations regardless of order', function () {
    [$user, $company] = memberWithRole(SystemRole::Manager);
    actAsCompany($company);
    $parent = Product::factory()->variable()->create();
    Product::factory()->variantOf($parent)->create(['variant_attributes' => ['color' => 'Red', 'size' => 'M']]);

    $this->actingAs($user)
        ->post(route('catalog.products.variants.store', $parent), variantPayload())
        ->assertSessionHasErrors('rule');
});

it('does not allow variants on simple products', function () {
    [$user, $company] = memberWithRole(SystemRole::Manager);
    $simple = tenant()->run($company, fn () => Product::factory()->create());

    $this->actingAs($user)
        ->post(route('catalog.products.variants.store', $simple), variantPayload())
        ->assertSessionHasErrors('rule');
});

it('requires at least one attribute with unique names', function () {
    [$user, $company] = memberWithRole(SystemRole::Manager);
    $parent = tenant()->run($company, fn () => Product::factory()->variable()->create());

    $this->actingAs($user)
        ->post(route('catalog.products.variants.store', $parent), variantPayload(['attributes' => []]))
        ->assertSessionHasErrors('attributes');

    $this->actingAs($user)
        ->post(route('catalog.products.variants.store', $parent), variantPayload(['attributes' => [
            ['name' => 'Size', 'value' => 'M'],
            ['name' => 'size', 'value' => 'L'],
        ]]))
        ->assertSessionHasErrors('attributes.1.name');
});

it('updates and deletes a variant', function () {
    [$user, $company] = memberWithRole(SystemRole::Manager);
    actAsCompany($company);
    $parent = Product::factory()->variable()->create();
    $variant = Product::factory()->variantOf($parent)->create(['sku' => 'OLD']);

    $this->actingAs($user)
        ->put(route('catalog.products.variants.update', [$parent, $variant]), variantPayload(['sku' => 'NEW']))
        ->assertSessionHasNoErrors();

    actAsCompany($company);
    expect($variant->fresh()->sku)->toBe('NEW');

    $this->actingAs($user)
        ->delete(route('catalog.products.variants.destroy', [$parent, $variant]))
        ->assertSessionHasNoErrors();

    actAsCompany($company);
    expect($parent->variants()->count())->toBe(0);
});

it('only resolves a variant through its own parent', function () {
    [$user, $company] = memberWithRole(SystemRole::Manager);
    actAsCompany($company);
    $shirt = Product::factory()->variable()->create();
    $shoe = Product::factory()->variable()->create();
    $shoeVariant = Product::factory()->variantOf($shoe)->create();

    $this->actingAs($user)
        ->delete(route('catalog.products.variants.destroy', [$shirt, $shoeVariant]))
        ->assertNotFound();
});

it('does not edit variants through the product endpoint', function () {
    [$user, $company] = memberWithRole(SystemRole::Manager);
    actAsCompany($company);
    $parent = Product::factory()->variable()->create();
    $variant = Product::factory()->variantOf($parent)->create();

    $this->actingAs($user)
        ->put(route('catalog.products.update', $variant), [
            'name' => 'Hack', 'sku' => $variant->sku, 'cost' => '1', 'price' => '1',
            'tax_rate' => '0', 'min_stock' => 0, 'status' => 'active',
        ])
        ->assertSessionHasErrors('rule');
});
