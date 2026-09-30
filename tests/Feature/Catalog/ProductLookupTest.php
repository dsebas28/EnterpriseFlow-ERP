<?php

use App\Enums\SystemRole;
use App\Models\Product;

beforeEach(function () {
    [$this->seller, $this->company] = memberWithRole(SystemRole::Sales);

    tenant()->run($this->company, function () {
        Product::factory()->create(['name' => 'Office chair', 'sku' => 'CH-1', 'barcode' => '7701234567890', 'price' => 10000, 'tax_rate' => 1900]);
        Product::factory()->create(['name' => 'Desk', 'sku' => 'DK-1']);
        Product::factory()->inactive()->create(['name' => 'Old chair', 'sku' => 'CH-OLD']);
        // Templates are not stockable: only their variants go on documents.
        Product::factory()->variable()->create(['name' => 'Chair template', 'sku' => 'CH-TPL']);
    });
});

it('finds active stockable products by name, SKU or exact barcode', function () {
    $lookup = fn (string $q) => $this->actingAs($this->seller)->getJson(route('catalog.products.lookup', ['q' => $q]))->assertOk()->json();

    $chairs = $lookup('chair');

    expect($chairs)->toHaveCount(1)
        ->and($chairs[0])->toHaveKeys(['id', 'cost'])
        ->and($chairs[0])->toMatchArray(['sku' => 'CH-1', 'name' => 'Office chair', 'price' => '100.00', 'tax_rate' => '19.00'])
        ->and(array_column($lookup('dk-1'), 'sku'))->toBe(['DK-1'])
        ->and(array_column($lookup('7701234567890'), 'sku'))->toBe(['CH-1'])
        ->and($lookup('770123'))->toBe([]) // barcodes match exactly, not partially
        ->and(array_column($lookup(''), 'sku'))->toBe(['DK-1', 'CH-1']); // ordered by name
});

it('never returns products of another company', function () {
    [, $other] = memberWithRole(SystemRole::Owner);
    tenant()->run($other, fn () => Product::factory()->create(['name' => 'Foreign chair']));

    $this->actingAs($this->seller)
        ->getJson(route('catalog.products.lookup', ['q' => 'chair']))
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonMissing(['name' => 'Foreign chair']);
});

it('requires a catalog, purchasing or sales permission', function () {
    [$member] = companyWithMember(null);
    $this->company->users()->attach($member->id, ['status' => 'active', 'joined_at' => now()]);

    $this->actingAs($member)
        ->withSession(['current_company_id' => $this->company->id])
        ->getJson(route('catalog.products.lookup'))
        ->assertForbidden();
});
