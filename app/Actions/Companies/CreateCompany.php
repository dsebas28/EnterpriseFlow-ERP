<?php

namespace App\Actions\Companies;

use App\DTOs\CompanyData;
use App\Enums\MembershipStatus;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Creates a company and makes the given user its first member.
 */
final class CreateCompany
{
    public function handle(User $owner, CompanyData $data): Company
    {
        return DB::transaction(function () use ($owner, $data): Company {
            $company = Company::create($data->toAttributes());

            $company->users()->attach($owner->getKey(), [
                'status' => MembershipStatus::Active->value,
                'joined_at' => now(),
            ]);

            return $company;
        });
    }
}
