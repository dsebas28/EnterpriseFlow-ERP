<?php

namespace App\Actions\Team;

use App\Enums\MembershipStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Invitation;
use App\Models\Membership;
use App\Models\Role;
use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use App\Support\Tenancy\CompanyScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Turns a pending invitation into an active membership with the invited role.
 */
final class AcceptInvitation
{
    public function __construct(private readonly PermissionResolver $permissions) {}

    public function handle(Invitation $invitation, User $user): Membership
    {
        if (Str::lower($user->email) !== Str::lower($invitation->email)) {
            throw new BusinessRuleViolation('This invitation was sent to a different email address.');
        }

        return DB::transaction(function () use ($invitation, $user): Membership {
            // Lock the row: two concurrent accept requests cannot both succeed.
            $locked = Invitation::withoutGlobalScope(CompanyScope::class)
                ->lockForUpdate()
                ->findOrFail($invitation->id);

            if (! $locked->isPending()) {
                throw new BusinessRuleViolation('This invitation is no longer valid.');
            }

            $membership = Membership::query()->firstOrNew([
                'company_id' => $locked->company_id,
                'user_id' => $user->id,
            ]);
            $membership->forceFill([
                'status' => MembershipStatus::Active,
                'invited_by' => $locked->invited_by,
                'joined_at' => $membership->joined_at ?? now(),
            ])->save();

            $role = Role::withoutGlobalScope(CompanyScope::class)->findOrFail($locked->role_id);
            $membership->syncRoles([$role]);

            $locked->forceFill(['accepted_at' => now()])->save();
            $this->permissions->flush();

            return $membership;
        });
    }
}
