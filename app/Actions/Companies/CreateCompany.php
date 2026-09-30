<?php

namespace App\Actions\Companies;

use App\Actions\Roles\ProvisionSystemRoles;
use App\Actions\Warehouses\SaveWarehouse;
use App\DTOs\CompanyData;
use App\Enums\MembershipStatus;
use App\Enums\SystemRole;
use App\Models\Company;
use App\Models\Membership;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Creates a company with its default roles and makes the given user its
 * Owner. Runs in one transaction: a company never exists without an owner.
 */
final class CreateCompany
{
    public function __construct(
        private readonly ProvisionSystemRoles $provisionRoles,
        private readonly SaveWarehouse $saveWarehouse,
        private readonly TenantContext $tenant,
    ) {}

    public function handle(User $owner, CompanyData $data): Company
    {
        return DB::transaction(function () use ($owner, $data): Company {
            $company = Company::create($data->toAttributes());

            $membership = new Membership;
            $membership->forceFill([
                'company_id' => $company->id,
                'user_id' => $owner->id,
                'status' => MembershipStatus::Active,
                'joined_at' => now(),
            ])->save();

            $roles = $this->provisionRoles->handle($company);
            $membership->syncRoles([$roles[SystemRole::Owner->value]]);

            // Every company starts with one default warehouse so stock can be
            // recorded from day one.
            $this->tenant->run($company, fn () => $this->saveWarehouse->handle(null, [
                'code' => 'MAIN',
                'name' => 'Main warehouse',
                'city' => $company->city,
                'is_default' => true,
            ]));

            return $company;
        });
    }
}
