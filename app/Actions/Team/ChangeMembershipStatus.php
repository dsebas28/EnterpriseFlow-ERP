<?php

namespace App\Actions\Team;

use App\Enums\MembershipStatus;
use App\Models\Membership;
use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use App\Services\Team\MembershipGuard;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Suspends or reactivates a member *in this company*. The user's global
 * account is untouched: they may still work in other companies.
 */
final class ChangeMembershipStatus
{
    public function __construct(
        private readonly MembershipGuard $guard,
        private readonly PermissionResolver $permissions,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(User $actor, Membership $membership, MembershipStatus $status): void
    {
        $this->guard->ensureNotSelf($actor, $membership);
        $this->guard->ensureCanTouchOwnership($actor, $membership);

        if ($status === MembershipStatus::Suspended) {
            $this->guard->ensureNotLastOwner($membership);
        }

        DB::transaction(function () use ($membership, $status): void {
            $previous = $membership->status;
            $membership->forceFill(['status' => $status])->save();

            $this->audit->record(
                $status === MembershipStatus::Suspended ? 'member.suspended' : 'member.reactivated',
                $membership,
                ['user_id' => $membership->user_id, 'status' => $previous->value],
                ['user_id' => $membership->user_id, 'status' => $status->value],
            );
        });

        $this->permissions->flush();
    }
}
