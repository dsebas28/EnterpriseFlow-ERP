<?php

namespace App\Services\Notifications;

use App\Enums\MembershipStatus;
use App\Enums\Permission;
use App\Enums\SystemRole;
use App\Enums\UserStatus;
use App\Models\Company;
use App\Models\User;
use App\Notifications\CompanyNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\Notification;
use LogicException;

/**
 * Delivers company notifications to the people allowed to act on them.
 *
 * Recipients are active users with an active membership whose roles grant
 * the category's permission (Owners implicitly hold them all), resolved in
 * one query. Notifying someone never reveals more than they could see.
 */
final class Notifier
{
    /**
     * @param  int|null  $except  usually the actor: nobody is told about what they just did
     */
    public function toPermittedMembers(CompanyNotification $notification, ?int $except = null): void
    {
        $permission = $notification->category()->permission()
            ?? throw new LogicException($notification::class.' targets specific users, not a permission.');

        $recipients = $this->membersWith($notification->companyId, $permission)
            ->when($except !== null, fn (Collection $users) => $users->except([$except]));

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, $notification);
        }
    }

    /**
     * @return Collection<int, User>
     */
    public function membersWith(Company|string $company, Permission $permission): Collection
    {
        $companyId = $company instanceof Company ? $company->id : $company;

        return User::query()
            ->where('status', UserStatus::Active)
            ->whereIn('id', fn (Builder $query) => $query
                ->select('m.user_id')
                ->from('company_user as m')
                ->join('membership_role as mr', 'mr.membership_id', '=', 'm.id')
                ->join('roles as r', 'r.id', '=', 'mr.role_id')
                ->leftJoin('role_permissions as rp', fn (JoinClause $join) => $join
                    ->on('rp.role_id', '=', 'r.id')
                    ->where('rp.permission', $permission->value))
                ->where('m.company_id', $companyId)
                ->where('m.status', MembershipStatus::Active->value)
                ->where(fn (Builder $grant) => $grant
                    ->whereNotNull('rp.permission')
                    ->orWhere(fn (Builder $owner) => $owner
                        ->where('r.is_system', true)
                        ->where('r.slug', SystemRole::Owner->value))))
            ->orderBy('id')
            ->get();
    }
}
