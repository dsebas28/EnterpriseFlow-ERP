<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CompanyPolicy
{
    /**
     * Whether the user may operate inside the company. Non-members get a
     * 404 rather than a 403 so company ids cannot be probed.
     */
    public function access(User $user, Company $company): Response
    {
        return $user->isActiveMemberOf($company)
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
