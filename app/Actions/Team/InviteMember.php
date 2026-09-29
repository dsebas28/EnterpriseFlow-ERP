<?php

namespace App\Actions\Team;

use App\Exceptions\BusinessRuleViolation;
use App\Models\Company;
use App\Models\Invitation;
use App\Models\Role;
use App\Models\User;
use App\Notifications\CompanyInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Invites an email address to the active company with a role.
 *
 * A new pending invitation replaces any previous pending one for the same
 * email, so re-sending never leaves several valid tokens around.
 */
final class InviteMember
{
    public function handle(Company $company, User $inviter, string $email, Role $role): Invitation
    {
        $email = Str::lower(trim($email));

        $alreadyMember = $company->users()
            ->whereRaw('lower(users.email) = ?', [$email])
            ->exists();

        if ($alreadyMember) {
            throw new BusinessRuleViolation('This person is already a member of the company.');
        }

        if ($role->isOwner() && ! $inviter->isSuperAdmin() && ! $inviter->membershipIn($company)?->isOwner()) {
            throw new BusinessRuleViolation('Only a company owner can invite new owners.');
        }

        $token = Str::random(64);

        $invitation = DB::transaction(function () use ($email, $role, $inviter, $token): Invitation {
            Invitation::pending()->where('email', $email)->update(['revoked_at' => now()]);

            $invitation = new Invitation(['email' => $email, 'role_id' => $role->id]);
            $invitation->forceFill([
                'token_hash' => Invitation::hashToken($token),
                'invited_by' => $inviter->id,
                'expires_at' => now()->addDays(Invitation::TTL_DAYS),
            ])->save();

            return $invitation;
        });

        // Queued notification; dispatched after commit so a rolled-back
        // invitation never produces an email.
        Notification::route('mail', $email)->notify(
            (new CompanyInvitation($invitation, $token))->afterCommit(),
        );

        return $invitation;
    }
}
