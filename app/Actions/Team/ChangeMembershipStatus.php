<?php

namespace App\Actions\Team;

use App\Enums\MembershipStatus;
use App\Models\Membership;
use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use App\Services\Team\MembershipGuard;

/**
 * Suspends or reactivates a member *in this company*. The user's global
 * account is untouched: they may still work in other companies.
 */
final class ChangeMembershipStatus
{
    public function __construct(
        private readonly MembershipGuard $guard,
        private readonly PermissionResolver $permissions,
    ) {}

    public function handle(User $actor, Membership $membership, MembershipStatus $status): void
    {
        $this->guard->ensureNotSelf($actor, $membership);
        $this->guard->ensureCanTouchOwnership($actor, $membership);

        if ($status === MembershipStatus::Suspended) {
            $this->guard->ensureNotLastOwner($membership);
        }

        $membership->forceFill(['status' => $status])->save();

        $this->permissions->flush();
    }
}
