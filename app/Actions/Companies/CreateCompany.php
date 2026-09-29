<?php

namespace App\Actions\Companies;

use App\Actions\Roles\ProvisionSystemRoles;
use App\DTOs\CompanyData;
use App\Enums\MembershipStatus;
use App\Enums\SystemRole;
use App\Models\Company;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Creates a company with its default roles and makes the given user its
 * Owner. Runs in one transaction: a company never exists without an owner.
 */
final class CreateCompany
{
    public function __construct(private readonly ProvisionSystemRoles $provisionRoles) {}

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

            return $company;
        });
    }
}
