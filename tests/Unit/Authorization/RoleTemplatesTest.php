<?php

use App\Enums\Permission;
use App\Enums\SystemRole;

it('uses module.action names for every permission', function () {
    foreach (Permission::values() as $value) {
        expect($value)->toMatch('/^[a-z]+\.[a-z]+$/');
    }
});

it('groups permissions by module', function () {
    expect(Permission::grouped())
        ->toHaveKey('sales')
        ->and(Permission::grouped()['sales'])->toContain('sales.cancel');
});

it('grants no duplicate permissions in role templates', function (SystemRole $role) {
    $values = array_map(fn (Permission $p) => $p->value, $role->defaultPermissions());

    expect($values)->toBe(array_values(array_unique($values)));
})->with(SystemRole::cases());

it('keeps team management away from operational roles', function (SystemRole $role) {
    expect($role->defaultPermissions())
        ->not->toContain(Permission::UsersManage)
        ->not->toContain(Permission::RolesManage)
        ->not->toContain(Permission::CompanySettings);
})->with([SystemRole::Manager, SystemRole::Accountant, SystemRole::Sales, SystemRole::Warehouse, SystemRole::Employee]);

it('keeps the employee role read-only', function () {
    foreach (SystemRole::Employee->defaultPermissions() as $permission) {
        expect($permission->value)->toEndWith('.view');
    }
});
