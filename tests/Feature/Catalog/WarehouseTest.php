<?php

use App\Actions\Companies\CreateCompany;
use App\DTOs\CompanyData;
use App\Enums\SystemRole;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

it('creates a default main warehouse with every new company', function () {
    $company = app(CreateCompany::class)->handle(User::factory()->create(), new CompanyData(
        name: 'Acme', country: 'CO', currency: 'COP', timezone: 'America/Bogota',
    ));

    actAsCompany($company);
    $warehouse = Warehouse::sole();

    expect($warehouse->code)->toBe('MAIN')->and($warehouse->is_default)->toBeTrue();
});

it('lists the warehouses of the company', function () {
    [$user, $company] = memberWithRole(SystemRole::Warehouse);
    tenant()->run($company, fn () => Warehouse::factory()->count(2)->create());

    $this->actingAs($user)
        ->get(route('inventory.warehouses.index'))
        ->assertInertia(fn (Assert $page) => $page->component('inventory/Warehouses')->has('warehouses.data', 2));
});

it('creates a warehouse with a normalised unique code', function () {
    [$user, $company] = memberWithRole(SystemRole::Manager);

    $this->actingAs($user)
        ->post(route('inventory.warehouses.store'), ['code' => 'north', 'name' => 'Almacén Norte'])
        ->assertSessionHasNoErrors();

    $this->actingAs($user)
        ->post(route('inventory.warehouses.store'), ['code' => 'NORTH', 'name' => 'Duplicate'])
        ->assertSessionHasErrors('code');

    actAsCompany($company);
    expect(Warehouse::sole()->code)->toBe('NORTH');
});

it('makes the first warehouse the default', function () {
    [$user, $company] = memberWithRole(SystemRole::Manager);

    $this->actingAs($user)->post(route('inventory.warehouses.store'), ['code' => 'A', 'name' => 'First']);

    expect(tenant()->run($company, fn () => Warehouse::sole()->is_default))->toBeTrue();
});

it('moves the default flag when another warehouse becomes default', function () {
    [$user, $company] = memberWithRole(SystemRole::Manager);
    [$main, $south] = tenant()->run($company, fn () => [
        Warehouse::factory()->default()->create(),
        Warehouse::factory()->create(),
    ]);

    $this->actingAs($user)
        ->put(route('inventory.warehouses.update', $south), ['code' => $south->code, 'name' => $south->name, 'is_default' => true])
        ->assertSessionHasNoErrors();

    actAsCompany($company);
    expect($south->fresh()->is_default)->toBeTrue()
        ->and($main->fresh()->is_default)->toBeFalse();
});

it('guarantees a single default warehouse at the database level', function () {
    [, $company] = memberWithRole(SystemRole::Manager);
    actAsCompany($company);
    Warehouse::factory()->default()->create();
    $second = Warehouse::factory()->create();

    DB::table('warehouses')->where('id', $second->id)->update(['is_default' => true]);
})->throws(QueryException::class);

it('does not delete or deactivate the default warehouse', function () {
    [$user, $company] = memberWithRole(SystemRole::Manager);
    $main = tenant()->run($company, fn () => Warehouse::factory()->default()->create());

    $this->actingAs($user)->delete(route('inventory.warehouses.destroy', $main))->assertSessionHasErrors('rule');
    $this->actingAs($user)
        ->put(route('inventory.warehouses.update', $main), ['code' => $main->code, 'name' => $main->name, 'is_active' => false])
        ->assertSessionHasErrors('rule');
});

it('deletes a non default warehouse', function () {
    [$user, $company] = memberWithRole(SystemRole::Manager);
    $extra = tenant()->run($company, fn () => Warehouse::factory()->create());

    $this->actingAs($user)->delete(route('inventory.warehouses.destroy', $extra))->assertSessionHasNoErrors();

    expect(tenant()->run($company, fn () => Warehouse::count()))->toBe(0);
});

it('isolates warehouses between companies', function () {
    [$user] = memberWithRole(SystemRole::Manager);
    [, $other] = memberWithRole(SystemRole::Owner);
    $foreign = tenant()->run($other, fn () => Warehouse::factory()->create());

    $this->actingAs($user)
        ->put(route('inventory.warehouses.update', $foreign), ['code' => 'X', 'name' => 'Hijack'])
        ->assertNotFound();
    $this->actingAs($user)->delete(route('inventory.warehouses.destroy', $foreign))->assertNotFound();
});

it('requires warehouses.manage to change warehouses', function () {
    [$user] = memberWithRole(SystemRole::Warehouse);

    $this->actingAs($user)
        ->post(route('inventory.warehouses.store'), ['code' => 'X', 'name' => 'Nope'])
        ->assertForbidden();
});
