<?php

use App\Actions\Companies\CreateCompany;
use App\DTOs\CompanyData;
use App\Enums\MembershipStatus;
use App\Enums\Permission;
use App\Enums\SystemRole;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

describe('provisioning', function () {
    it('creates the system roles and makes the creator Owner', function () {
        $user = User::factory()->create();

        $company = app(CreateCompany::class)->handle($user, new CompanyData(
            name: 'Acme', country: 'CO', currency: 'COP', timezone: 'America/Bogota',
        ));

        actAsCompany($company);

        expect(Role::pluck('slug')->sort()->values()->all())
            ->toBe(collect(SystemRole::cases())->pluck('value')->sort()->values()->all())
            ->and($user->membershipIn($company)->roles->pluck('slug')->all())->toBe(['owner'])
            ->and($user->hasPermission(Permission::CompanySettings))->toBeTrue();
    });

    it('is idempotent and preserves customised permissions', function () {
        [, $company] = memberWithRole(SystemRole::Sales);
        actAsCompany($company);

        Role::firstWhere('slug', 'sales')->syncPermissions([Permission::SalesView]);

        memberWithRole(SystemRole::Sales, $company); // provisions again

        expect(Role::count())->toBe(count(SystemRole::cases()))
            ->and(Role::firstWhere('slug', 'sales')->permissions()->all())->toBe([Permission::SalesView]);
    });
});

describe('evaluation', function () {
    it('grants exactly the permissions of the role', function () {
        [$user, $company] = memberWithRole(SystemRole::Warehouse);
        actAsCompany($company);

        expect($user->hasPermission(Permission::InventoryAdjust))->toBeTrue()
            ->and($user->hasPermission(Permission::SalesCancel))->toBeFalse()
            ->and($user->can('inventory.adjust'))->toBeTrue()
            ->and($user->can('sales.cancel'))->toBeFalse();
    });

    it('gives the owner every permission, including ones added later', function () {
        [$user, $company] = memberWithRole(SystemRole::Owner);
        actAsCompany($company);

        expect($user->currentPermissions())->toEqualCanonicalizing(Permission::values());
        expect(DB::table('role_permissions')->where('role_id', Role::firstWhere('slug', 'owner')->id)->count())->toBe(0);
    });

    it('merges permissions of multiple roles', function () {
        [$user, $company] = memberWithRole(SystemRole::Employee);
        actAsCompany($company);
        $membership = $user->membershipIn($company);

        $membership->syncRoles(Role::whereIn('slug', ['employee', 'accountant'])->get());
        app(PermissionResolver::class)->flush();

        expect($user->hasPermission(Permission::PaymentsVoid))->toBeTrue()
            ->and($user->hasPermission(Permission::CustomersView))->toBeTrue();
    });

    it('evaluates permissions per company', function () {
        $user = User::factory()->create();
        [, $companyA] = memberWithRole(SystemRole::Employee, user: $user);
        [, $companyB] = memberWithRole(SystemRole::Administrator, user: $user);

        actAsCompany($companyA);
        expect($user->hasPermission(Permission::UsersManage))->toBeFalse();

        actAsCompany($companyB);
        expect($user->hasPermission(Permission::UsersManage))->toBeTrue();
    });

    it('grants nothing without an active company', function () {
        [$user] = memberWithRole(SystemRole::Owner);

        expect($user->hasPermission(Permission::ProductsView))->toBeFalse()
            ->and($user->currentPermissions())->toBe([]);
    });

    it('grants nothing through a suspended membership', function () {
        [$user, $company] = memberWithRole(SystemRole::Administrator);
        $company->users()->updateExistingPivot($user->id, ['status' => MembershipStatus::Suspended->value]);
        actAsCompany($company);

        expect($user->hasPermission(Permission::ProductsView))->toBeFalse();
    });

    it('lets platform super admins through every gate', function () {
        $admin = User::factory()->superAdmin()->create();

        expect(Gate::forUser($admin)->allows('sales.cancel'))->toBeTrue();
    });

    it('resolves permissions with a single query per request', function () {
        [$user, $company] = memberWithRole(SystemRole::Manager);
        actAsCompany($company);

        DB::enableQueryLog();
        foreach (Permission::cases() as $permission) {
            $user->hasPermission($permission);
        }

        expect(DB::getQueryLog())->toHaveCount(1);
    });
});

describe('integrity', function () {
    it('rejects assigning a role from another company at the database level', function () {
        [$user, $companyA] = memberWithRole(SystemRole::Employee);
        $companyB = Company::factory()->create();
        $foreignRole = tenant()->run($companyB, fn () => Role::factory()->create(['company_id' => $companyB->id]));

        DB::table('membership_role')->insert([
            'company_id' => $companyA->id,
            'membership_id' => $user->membershipIn($companyA)->id,
            'role_id' => $foreignRole->id,
        ]);
    })->throws(QueryException::class);
});

describe('http', function () {
    beforeEach(function () {
        Route::middleware(['web', 'auth', 'tenant', 'can:sales.cancel'])
            ->get('/_test/cancel-sale', fn () => 'cancelled');
    });

    it('allows the route when the member holds the permission', function () {
        [$user] = memberWithRole(SystemRole::Manager);

        $this->actingAs($user)->get('/_test/cancel-sale')->assertOk();
    });

    it('forbids the route when the member lacks the permission', function () {
        [$user] = memberWithRole(SystemRole::Sales);

        $this->actingAs($user)->get('/_test/cancel-sale')->assertForbidden();
    });

    it('shares the current permissions with the frontend', function () {
        [$user] = memberWithRole(SystemRole::Employee);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.permissions', fn ($permissions) => collect($permissions)->sort()->values()->all()
                    === collect(SystemRole::Employee->defaultPermissions())->pluck('value')->sort()->values()->all()));
    });
});
