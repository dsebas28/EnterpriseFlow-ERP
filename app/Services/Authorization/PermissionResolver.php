<?php

namespace App\Services\Authorization;

use App\Enums\MembershipStatus;
use App\Enums\Permission;
use App\Enums\SystemRole;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Resolves the effective permissions of a user inside a company: the union
 * of the permissions of every role on their *active* membership.
 *
 * Registered as a scoped binding and memoised per (user, company), so a
 * request that runs dozens of authorization checks hits the database once.
 */
final class PermissionResolver
{
    /**
     * @var array<string, array<string, true>>
     */
    private array $resolved = [];

    public function has(User $user, Company $company, Permission $permission): bool
    {
        return isset($this->permissionsFor($user, $company)[$permission->value]);
    }

    /**
     * @return array<string, true> permission value => true
     */
    public function permissionsFor(User $user, Company $company): array
    {
        return $this->resolved[$user->getKey().'|'.$company->getKey()] ??= $this->load($user, $company);
    }

    /**
     * Forget memoised permissions, e.g. after roles changed mid-request.
     */
    public function flush(): void
    {
        $this->resolved = [];
    }

    /**
     * @return array<string, true>
     */
    private function load(User $user, Company $company): array
    {
        $rows = DB::table('company_user as m')
            ->join('membership_role as mr', 'mr.membership_id', '=', 'm.id')
            ->join('roles as r', 'r.id', '=', 'mr.role_id')
            ->leftJoin('role_permissions as rp', 'rp.role_id', '=', 'r.id')
            ->where('m.user_id', $user->getKey())
            ->where('m.company_id', $company->getKey())
            ->where('m.status', MembershipStatus::Active->value)
            ->get(['r.slug', 'r.is_system', 'rp.permission']);

        $isOwner = $rows->contains(
            fn (object $row) => (bool) $row->is_system && $row->slug === SystemRole::Owner->value,
        );

        $permissions = $isOwner
            ? Permission::values()
            // Ignore stale names left behind by permissions removed from the enum.
            : $rows->pluck('permission')->filter()->intersect(Permission::values())->all();

        return array_fill_keys($permissions, true);
    }
}
