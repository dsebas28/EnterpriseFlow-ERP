<?php

use App\Enums\SystemRole;
use App\Models\Category;
use App\Models\Product;
use Inertia\Testing\AssertableInertia as Assert;

it('lists categories as an indented tree', function () {
    [$user, $company] = memberWithRole(SystemRole::Manager);
    actAsCompany($company);
    $root = Category::factory()->create(['name' => 'Electronics']);
    Category::factory()->create(['name' => 'Audio', 'parent_id' => $root->id]);
    Category::factory()->create(['name' => 'Books']);

    $this->actingAs($user)
        ->get(route('catalog.categories.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('catalog/Categories')
            ->where('categories.0.name', 'Books')
            ->where('categories.1.name', 'Electronics')
            ->where('categories.2.name', 'Audio')
            ->where('categories.2.depth', 1));
});

it('creates categories with unique slugs per company', function () {
    [$user, $company] = memberWithRole(SystemRole::Manager);

    $this->actingAs($user)->post(route('catalog.categories.store'), ['name' => 'Home & Garden'])->assertSessionHasNoErrors();
    $this->actingAs($user)->post(route('catalog.categories.store'), ['name' => 'Home & Garden'])->assertSessionHasNoErrors();

    actAsCompany($company);
    expect(Category::pluck('slug')->sort()->values()->all())->toBe(['home-garden', 'home-garden-2']);
});

it('prevents cycles in the hierarchy', function () {
    [$user, $company] = memberWithRole(SystemRole::Manager);
    actAsCompany($company);
    $root = Category::factory()->create();
    $child = Category::factory()->create(['parent_id' => $root->id]);

    $this->actingAs($user)
        ->put(route('catalog.categories.update', $root), ['name' => $root->name, 'parent_id' => $child->id])
        ->assertSessionHasErrors('rule');

    $this->actingAs($user)
        ->put(route('catalog.categories.update', $root), ['name' => $root->name, 'parent_id' => $root->id])
        ->assertSessionHasErrors('rule');
});

it('does not delete categories with products or subcategories', function () {
    [$user, $company] = memberWithRole(SystemRole::Manager);
    actAsCompany($company);
    $withProducts = Category::factory()->create();
    Product::factory()->create(['category_id' => $withProducts->id]);
    $withChildren = Category::factory()->create();
    Category::factory()->create(['parent_id' => $withChildren->id]);

    $this->actingAs($user)->delete(route('catalog.categories.destroy', $withProducts))->assertSessionHasErrors('rule');
    $this->actingAs($user)->delete(route('catalog.categories.destroy', $withChildren))->assertSessionHasErrors('rule');
});

it('deletes an empty category', function () {
    [$user, $company] = memberWithRole(SystemRole::Manager);
    $category = tenant()->run($company, fn () => Category::factory()->create());

    $this->actingAs($user)->delete(route('catalog.categories.destroy', $category))->assertSessionHasNoErrors();

    expect(tenant()->run($company, fn () => Category::count()))->toBe(0);
});

it('rejects a parent from another company', function () {
    [, $other] = memberWithRole(SystemRole::Owner);
    $foreign = tenant()->run($other, fn () => Category::factory()->create());
    [$user] = memberWithRole(SystemRole::Manager);

    $this->actingAs($user)
        ->post(route('catalog.categories.store'), ['name' => 'Sneaky', 'parent_id' => $foreign->id])
        ->assertSessionHasErrors('parent_id');
});

it('requires categories.manage to change categories', function () {
    [$user] = memberWithRole(SystemRole::Sales);

    $this->actingAs($user)->post(route('catalog.categories.store'), ['name' => 'X'])->assertForbidden();
});
