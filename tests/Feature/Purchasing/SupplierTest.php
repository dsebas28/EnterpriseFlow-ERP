<?php

use App\Enums\SystemRole;
use App\Models\Supplier;
use Inertia\Testing\AssertableInertia as Assert;

function supplierPayload(array $overrides = []): array
{
    return [
        'kind' => 'company',
        'name' => 'Distribuidora Andina',
        'tax_id' => '900555111-2',
        'contact_name' => 'Laura Gómez',
        'email' => 'compras@andina.test',
        'phone' => '+57 300 000 0000',
        'city' => 'Medellín',
        'country' => 'CO',
        'status' => 'active',
        ...$overrides,
    ];
}

it('lists and searches suppliers of the company only', function () {
    [$user, $company] = memberWithRole(SystemRole::Manager);
    tenant()->run($company, function () {
        Supplier::factory()->create(['name' => 'Andina Foods']);
        Supplier::factory()->create(['name' => 'Global Parts']);
    });
    [, $other] = memberWithRole(SystemRole::Owner);
    tenant()->run($other, fn () => Supplier::factory()->create(['name' => 'Andina Clone']));

    $this->actingAs($user)
        ->get(route('purchasing.suppliers.index', ['search' => 'andina']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('purchasing/Suppliers')
            ->has('suppliers.data', 1)
            ->where('suppliers.data.0.name', 'Andina Foods'));
});

it('creates and updates a supplier', function () {
    [$user, $company] = memberWithRole(SystemRole::Manager);

    $this->actingAs($user)->post(route('purchasing.suppliers.store'), supplierPayload())->assertSessionHasNoErrors();

    $supplier = tenant()->run($company, fn () => Supplier::sole());

    $this->actingAs($user)
        ->put(route('purchasing.suppliers.update', $supplier), supplierPayload(['status' => 'inactive']))
        ->assertSessionHasNoErrors();

    expect(tenant()->run($company, fn () => $supplier->fresh()->status->value))->toBe('inactive');
});

it('enforces a unique tax id per company but not across companies', function () {
    [$user, $company] = memberWithRole(SystemRole::Manager);
    [, $other] = memberWithRole(SystemRole::Owner);
    tenant()->run($other, fn () => Supplier::factory()->create(['tax_id' => '900555111-2']));

    $this->actingAs($user)->post(route('purchasing.suppliers.store'), supplierPayload())->assertSessionHasNoErrors();
    $this->actingAs($user)->post(route('purchasing.suppliers.store'), supplierPayload(['name' => 'Dup']))->assertSessionHasErrors('tax_id');
});

it('requires supplier permissions', function () {
    [$user] = memberWithRole(SystemRole::Sales);

    $this->actingAs($user)->get(route('purchasing.suppliers.index'))->assertForbidden();
    $this->actingAs($user)->post(route('purchasing.suppliers.store'), supplierPayload())->assertForbidden();
});

it('cannot modify suppliers of another company', function () {
    [$user] = memberWithRole(SystemRole::Manager);
    [, $other] = memberWithRole(SystemRole::Owner);
    $foreign = tenant()->run($other, fn () => Supplier::factory()->create());

    $this->actingAs($user)->put(route('purchasing.suppliers.update', $foreign), supplierPayload())->assertNotFound();
    $this->actingAs($user)->delete(route('purchasing.suppliers.destroy', $foreign))->assertNotFound();
});
