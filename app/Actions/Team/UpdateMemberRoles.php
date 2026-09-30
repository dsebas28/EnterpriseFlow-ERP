<?php

namespace App\Actions\Team;

use App\Models\Membership;
use App\Models\Role;
use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use App\Services\Team\MembershipGuard;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class UpdateMemberRoles
{
    public function __construct(
        private readonly MembershipGuard $guard,
        private readonly PermissionResolver $permissions,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  Collection<int, Role>  $roles
     */
    public function handle(User $actor, Membership $membership, Collection $roles): void
    {
        $this->guard->ensureNotSelf($actor, $membership);
        $this->guard->ensureCanTouchOwnership($actor, $membership, $roles);

        $losesOwner = $membership->isOwner() && ! $roles->contains(fn (Role $role) => $role->isOwner());
        if ($losesOwner) {
            $this->guard->ensureNotLastOwner($membership);
        }

        DB::transaction(function () use ($membership, $roles): void {
            $before = $membership->roles()->orderBy('name')->pluck('name')->all();
            $membership->syncRoles($roles);

            $this->audit->record('member.roles_changed', $membership,
                ['user_id' => $membership->user_id, 'roles' => $before],
                ['user_id' => $membership->user_id, 'roles' => $roles->sortBy('name')->pluck('name')->values()->all()],
            );
        });

        $this->permissions->flush();
    }
}
