<?php

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Enums\SystemRole;
use App\Models\Category;
use App\Models\Product;
use Inertia\Testing\AssertableInertia as Assert;

function productPayload(array $overrides = []): array
{
    return [
        'type' => 'simple',
        'name' => 'Wireless Mouse',
        'sku' => 'wm-001',
        'barcode' => '7701234567890',
        'description' => 'Ergonomic mouse',
        'category_id' => null,
        'cost' => '45000.50',
        'price' => '79900',
        'tax_rate' => '19',
        'min_stock' => 5,
        'status' => 'active',
        ...$overrides,
    ];
}

describe('listing', function () {
    it('lists catalogue products with pagination and hides variants', function () {
        [$user, $company] = memberWithRole(SystemRole::Employee);
        actAsCompany($company);
        Product::factory()->count(3)->create();
        $parent = Product::factory()->variable()->create();
        Product::factory()->variantOf($parent)->count(2)->create();

        $this->actingAs($user)
            ->get(route('catalog.products.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('catalog/products/Index')
                ->has('products.data', 4)
                ->where('products.meta.total', 4));
    });

    it('searches by name, sku, barcode and variant sku', function () {
        [$user, $company] = memberWithRole(SystemRole::Employee);
        actAsCompany($company);
        Product::factory()->create(['name' => 'Blue Chair', 'sku' => 'CH-1']);
        Product::factory()->create(['name' => 'Desk', 'sku' => 'DK-1', 'barcode' => '999111']);
        $shirt = Product::factory()->variable()->create(['name' => 'Shirt', 'sku' => 'SH']);
        Product::factory()->variantOf($shirt)->create(['sku' => 'SH-RED-M']);

        $search = fn (string $term) => $this->actingAs($user)
            ->get(route('catalog.products.index', ['search' => $term]))
            ->assertInertia(fn (Assert $page) => $page)
            ->viewData('page')['props']['products']['data'];

        expect(collect($search('blue'))->pluck('name')->all())->toBe(['Blue Chair'])
            ->and(collect($search('999111'))->pluck('name')->all())->toBe(['Desk'])
            ->and(collect($search('sh-red'))->pluck('name')->all())->toBe(['Shirt']);
    });

    it('filters by category including subcategories', function () {
        [$user, $company] = memberWithRole(SystemRole::Employee);
        actAsCompany($company);
        $electronics = Category::factory()->create(['name' => 'Electronics']);
        $audio = Category::factory()->create(['name' => 'Audio', 'parent_id' => $electronics->id]);
        Product::factory()->create(['name' => 'TV', 'category_id' => $electronics->id]);
        Product::factory()->create(['name' => 'Headphones', 'category_id' => $audio->id]);
        Product::factory()->create(['name' => 'Sofa']);

        $this->actingAs($user)
            ->get(route('catalog.products.index', ['category_id' => $electronics->id, 'sort' => 'name']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('products.data', 2)
                ->where('products.data.0.name', 'Headphones')
                ->where('products.data.1.name', 'TV'));
    });

    it('sorts only by allow-listed columns', function () {
        [$user, $company] = memberWithRole(SystemRole::Employee);
        actAsCompany($company);
        Product::factory()->create(['name' => 'Cheap', 'price' => 100]);
        Product::factory()->create(['name' => 'Pricey', 'price' => 900]);

        $this->actingAs($user)
            ->get(route('catalog.products.index', ['sort' => '-price']))
            ->assertInertia(fn (Assert $page) => $page->where('products.data.0.name', 'Pricey'));

        // An arbitrary column is ignored instead of reaching the SQL.
        $this->actingAs($user)
            ->get(route('catalog.products.index', ['sort' => 'password']))
            ->assertOk();
    });

    it('requires products.view', function () {
        [$user] = memberWithRole(SystemRole::Accountant);
        $role = tenant()->run($user->activeCompanies()->first(), fn () => App\Models\Role::firstWhere('slug', 'accountant'));
        $role->syncPermissions([]);

        $this->actingAs($user)->get(route('catalog.products.index'))->assertForbidden();
    });
});

describe('creating', function () {
    it('creates a product converting money to minor units', function () {
        [$user, $company] = memberWithRole(SystemRole::Manager);

        $this->actingAs($user)
            ->post(route('catalog.products.store'), productPayload())
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        actAsCompany($company);
        $product = Product::sole();

        expect($product->sku)->toBe('WM-001')
            ->and($product->cost)->toBe(4500050)
            ->and($product->price)->toBe(7990000)
            ->and($product->tax_rate)->toBe(1900)
            ->and($product->type)->toBe(ProductType::Simple)
            ->and($product->company_id)->toBe($company->id);
    });

    it('validates input', function () {
        [$user] = memberWithRole(SystemRole::Manager);

        $this->actingAs($user)
            ->post(route('catalog.products.store'), productPayload([
                'name' => '',
                'sku' => 'bad sku!',
                'price' => '10.999',
                'cost' => '-1',
                'tax_rate' => '150',
                'type' => 'variant',
                'status' => 'archived',
            ]))
            ->assertSessionHasErrors(['name', 'sku', 'price', 'cost', 'tax_rate', 'type', 'status']);
    });

    it('enforces unique sku and barcode per company', function () {
        [$user, $company] = memberWithRole(SystemRole::Manager);
        tenant()->run($company, fn () => Product::factory()->create(['sku' => 'WM-001', 'barcode' => '7701234567890']));

        $this->actingAs($user)
            ->post(route('catalog.products.store'), productPayload())
            ->assertSessionHasErrors(['sku', 'barcode']);
    });

    it('keeps the sku of a deleted product reserved', function () {
        [$user, $company] = memberWithRole(SystemRole::Manager);
        tenant()->run($company, fn () => Product::factory()->create(['sku' => 'WM-001'])->delete());

        $this->actingAs($user)
            ->post(route('catalog.products.store'), productPayload(['barcode' => null]))
            ->assertSessionHasErrors('sku');
    });

    it('allows the same sku in different companies', function () {
        [, $otherCompany] = memberWithRole(SystemRole::Owner);
        tenant()->run($otherCompany, fn () => Product::factory()->create(['sku' => 'WM-001', 'barcode' => '7701234567890']));
        [$user] = memberWithRole(SystemRole::Manager);

        $this->actingAs($user)
            ->post(route('catalog.products.store'), productPayload())
            ->assertSessionHasNoErrors();
    });

    it('rejects a category from another company', function () {
        [, $otherCompany] = memberWithRole(SystemRole::Owner);
        $foreignCategory = tenant()->run($otherCompany, fn () => Category::factory()->create());
        [$user] = memberWithRole(SystemRole::Manager);

        $this->actingAs($user)
            ->post(route('catalog.products.store'), productPayload(['category_id' => $foreignCategory->id]))
            ->assertSessionHasErrors('category_id');
    });

    it('forbids creation without products.create', function () {
        [$user] = memberWithRole(SystemRole::Sales);

        $this->actingAs($user)
            ->post(route('catalog.products.store'), productPayload())
            ->assertForbidden();
    });
});

describe('updating and deleting', function () {
    it('updates a product but never its type', function () {
        [$user, $company] = memberWithRole(SystemRole::Manager);
        $product = tenant()->run($company, fn () => Product::factory()->create(['sku' => 'WM-001']));

        $this->actingAs($user)
            ->put(route('catalog.products.update', $product), productPayload(['type' => null, 'name' => 'Renamed', 'status' => 'inactive']))
            ->assertSessionHasNoErrors();

        actAsCompany($company);
        expect($product->fresh()->name)->toBe('Renamed')
            ->and($product->fresh()->status)->toBe(ProductStatus::Inactive);

        $this->actingAs($user)
            ->put(route('catalog.products.update', $product), productPayload(['type' => 'variable']))
            ->assertSessionHasErrors('type');
    });

    it('soft deletes a product with its variants', function () {
        [$user, $company] = memberWithRole(SystemRole::Manager);
        actAsCompany($company);
        $parent = Product::factory()->variable()->create();
        $variant = Product::factory()->variantOf($parent)->create();

        $this->actingAs($user)
            ->delete(route('catalog.products.destroy', $parent))
            ->assertRedirect(route('catalog.products.index'));

        actAsCompany($company);
        expect(Product::count())->toBe(0)
            ->and(Product::withTrashed()->whereKey([$parent->id, $variant->id])->count())->toBe(2);
    });

    it('forbids deletion without products.delete', function () {
        [$user, $company] = memberWithRole(SystemRole::Sales);
        $product = tenant()->run($company, fn () => Product::factory()->create());

        $this->actingAs($user)->delete(route('catalog.products.destroy', $product))->assertForbidden();
    });
});

describe('tenant isolation', function () {
    it('company A cannot view, update or delete products of company B', function () {
        [$userA] = memberWithRole(SystemRole::Owner);
        [, $companyB] = memberWithRole(SystemRole::Owner);
        $productB = tenant()->run($companyB, fn () => Product::factory()->create(['name' => 'Secret B']));

        $this->actingAs($userA)->get(route('catalog.products.edit', $productB))->assertNotFound();
        $this->actingAs($userA)->put(route('catalog.products.update', $productB), productPayload())->assertNotFound();
        $this->actingAs($userA)->delete(route('catalog.products.destroy', $productB))->assertNotFound();

        $this->actingAs($userA)
            ->get(route('catalog.products.index', ['search' => 'Secret']))
            ->assertInertia(fn (Assert $page) => $page->has('products.data', 0));

        expect(tenant()->run($companyB, fn () => Product::whereKey($productB->id)->value('name')))->toBe('Secret B');
    });
});
