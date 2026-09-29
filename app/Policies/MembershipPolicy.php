<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Membership;
use App\Models\User;

class MembershipPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::UsersManage);
    }

    public function update(User $user, Membership $membership): bool
    {
        return $user->hasPermission(Permission::UsersManage);
    }
}
