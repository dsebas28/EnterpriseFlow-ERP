<?php

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
 * Create a company with an active member.
 *
 * @return array{0: User, 1: Company}
 */
function companyWithMember(?User $user = null): array
{
    $user ??= User::factory()->create();
    $company = Company::factory()->withMember($user)->create();

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
