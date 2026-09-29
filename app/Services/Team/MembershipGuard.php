<?php

namespace App\Services\Team;

use App\Enums\MembershipStatus;
use App\Enums\SystemRole;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Membership;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Business rules that protect a company's team from lock-out and
 * privilege escalation. Permissions decide *who may manage the team*;
 * these rules decide *which changes are allowed at all*.
 */
final class MembershipGuard
{
    /**
     * Nobody changes their own membership: prevents self-escalation and
     * accidentally locking yourself out.
     */
    public function ensureNotSelf(User $actor, Membership $target): void
    {
        if ($target->user_id === $actor->id) {
            throw new BusinessRuleViolation('You cannot change your own membership.');
        }
    }

    /**
     * Only an owner may modify another owner or grant/revoke the Owner role.
     *
     * @param  iterable<Role>  $newRoles
     */
    public function ensureCanTouchOwnership(User $actor, Membership $target, iterable $newRoles = []): void
    {
        $grantsOwner = collect($newRoles)->contains(fn (Role $role) => $role->isOwner());

        if (($target->isOwner() || $grantsOwner) && ! $this->actorIsOwner($actor, $target->company_id)) {
            throw new BusinessRuleViolation('Only a company owner can manage ownership.');
        }
    }

    /**
     * A company must always keep at least one active owner.
     */
    public function ensureNotLastOwner(Membership $target): void
    {
        if ($target->isOwner() && $this->activeOwnerCount($target->company_id) <= 1) {
            throw new BusinessRuleViolation('A company must keep at least one active owner.');
        }
    }

    private function actorIsOwner(User $actor, string $companyId): bool
    {
        if ($actor->isSuperAdmin()) {
            return true;
        }

        return $this->ownerMemberships($companyId)->where('m.user_id', $actor->id)->exists();
    }

    private function activeOwnerCount(string $companyId): int
    {
        return $this->ownerMemberships($companyId)->count();
    }

    private function ownerMemberships(string $companyId): Builder
    {
        return DB::table('company_user as m')
            ->join('membership_role as mr', 'mr.membership_id', '=', 'm.id')
            ->join('roles as r', 'r.id', '=', 'mr.role_id')
            ->where('m.company_id', $companyId)
            ->where('m.status', MembershipStatus::Active->value)
            ->where('r.is_system', true)
            ->where('r.slug', SystemRole::Owner->value);
    }
}
