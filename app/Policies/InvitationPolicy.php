<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Invitation;
use App\Models\User;

class InvitationPolicy
{
    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::UsersManage);
    }

    public function delete(User $user, Invitation $invitation): bool
    {
        return $user->hasPermission(Permission::UsersManage);
    }
}
