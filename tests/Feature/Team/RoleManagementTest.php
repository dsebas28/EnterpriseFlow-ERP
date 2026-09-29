<?php

use App\Enums\Permission;
use App\Enums\SystemRole;
use App\Models\Role;
use Inertia\Testing\AssertableInertia as Assert;

it('lists the company roles with the permission catalogue', function () {
    [$admin] = memberWithRole(SystemRole::Administrator);

    $this->actingAs($admin)
        ->get(route('team.roles.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('team/Roles')
            ->has('roles.data', count(SystemRole::cases()))
            ->has('permissionGroups.sales'));
});

it('creates a custom role', function () {
    [$admin, $company] = memberWithRole(SystemRole::Administrator);

    $this->actingAs($admin)
        ->post(route('team.roles.store'), [
            'name' => 'Cashier',
            'description' => 'Front desk',
            'permissions' => ['sales.view', 'sales.create', 'payments.create'],
        ])
        ->assertSessionHasNoErrors();

    actAsCompany($company);
    $role = Role::firstWhere('slug', 'cashier');

    expect($role->is_system)->toBeFalse()
        ->and($role->permissions()->map->value->sort()->values()->all())
        ->toBe(['payments.create', 'sales.create', 'sales.view']);
});

it('rejects unknown permissions', function () {
    [$admin] = memberWithRole(SystemRole::Administrator);

    $this->actingAs($admin)
        ->post(route('team.roles.store'), ['name' => 'Hacker', 'permissions' => ['platform.root']])
        ->assertSessionHasErrors('permissions.0');
});

it('adjusts the permissions of a system role but keeps its name', function () {
    [$admin, $company] = memberWithRole(SystemRole::Administrator);
    $sales = tenant()->run($company, fn () => Role::firstWhere('slug', 'sales'));

    $this->actingAs($admin)
        ->put(route('team.roles.update', $sales), ['name' => 'Renamed', 'permissions' => ['sales.view']])
        ->assertSessionHasNoErrors();

    actAsCompany($company);
    $sales->refresh();
    expect($sales->name)->toBe('Sales')
        ->and($sales->permissions()->all())->toBe([Permission::SalesView]);
});

it('never modifies the Owner role', function () {
    [$admin, $company] = memberWithRole(SystemRole::Administrator);
    $owner = tenant()->run($company, fn () => Role::firstWhere('slug', 'owner'));

    $this->actingAs($admin)
        ->put(route('team.roles.update', $owner), ['name' => 'Owner', 'permissions' => []])
        ->assertSessionHasErrors('rule');
});

it('does not delete system roles or roles in use', function () {
    [$admin, $company] = memberWithRole(SystemRole::Administrator);
    $system = tenant()->run($company, fn () => Role::firstWhere('slug', 'employee'));

    $this->actingAs($admin)->delete(route('team.roles.destroy', $system))->assertSessionHasErrors('rule');

    $custom = tenant()->run($company, function () use ($admin, $company) {
        $role = Role::create(['name' => 'Temp', 'slug' => 'temp']);
        $admin->membershipIn($company)->syncRoles([Role::firstWhere('slug', 'administrator'), $role]);

        return $role;
    });

    $this->actingAs($admin)->delete(route('team.roles.destroy', $custom))->assertSessionHasErrors('rule');
});

it('deletes an unused custom role', function () {
    [$admin, $company] = memberWithRole(SystemRole::Administrator);
    $custom = tenant()->run($company, fn () => Role::create(['name' => 'Temp', 'slug' => 'temp']));

    $this->actingAs($admin)->delete(route('team.roles.destroy', $custom))->assertSessionHasNoErrors();

    expect(tenant()->run($company, fn () => Role::whereKey($custom->id)->exists()))->toBeFalse();
});

it('forbids role management without roles.manage', function () {
    [$manager] = memberWithRole(SystemRole::Manager);

    $this->actingAs($manager)
        ->post(route('team.roles.store'), ['name' => 'X', 'permissions' => []])
        ->assertForbidden();
});

it('cannot touch roles of another company', function () {
    [$admin] = memberWithRole(SystemRole::Administrator);
    [, $other] = memberWithRole(SystemRole::Owner);
    $foreign = tenant()->run($other, fn () => Role::firstWhere('slug', 'sales'));

    $this->actingAs($admin)
        ->put(route('team.roles.update', $foreign), ['name' => 'Sales', 'permissions' => []])
        ->assertNotFound();

    $this->actingAs($admin)->delete(route('team.roles.destroy', $foreign))->assertNotFound();
});
