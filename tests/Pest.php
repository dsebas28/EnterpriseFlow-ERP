<?php

use App\Actions\Roles\ProvisionSystemRoles;
use App\Enums\SystemRole;
use App\Models\Company;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/**
 * Create a company with an active member (no roles).
 *
 * @return array{0: User, 1: Company}
 */
function companyWithMember(?User $user = null): array
{
    $user ??= User::factory()->create();
    $company = Company::factory()->withMember($user)->create();

    return [$user, $company];
}

/**
 * Create a company with its system roles and a member holding the given role.
 *
 * @return array{0: User, 1: Company}
 */
function memberWithRole(SystemRole $role, ?Company $company = null, ?User $user = null): array
{
    $user ??= User::factory()->create();
    $company ??= Company::factory()->create();

    $roles = app(ProvisionSystemRoles::class)->handle($company);

    if (! $user->membershipIn($company)) {
        $company->users()->attach($user->id, ['status' => 'active', 'joined_at' => now()]);
    }

    $user->membershipIn($company)->syncRoles([$roles[$role->value]]);

    return [$user, $company];
}

function tenant(): TenantContext
{
    return app(TenantContext::class);
}

/**
 * Activate a company in the TenantContext for code under test that runs
 * outside the HTTP middleware stack (actions, services, models).
 */
function actAsCompany(Company $company): Company
{
    tenant()->set($company);

    return $company;
}
