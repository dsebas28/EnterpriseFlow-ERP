<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::PaymentsView);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::PaymentsCreate);
    }

    public function void(User $user, Payment $payment): bool
    {
        return $user->hasPermission(Permission::PaymentsVoid);
    }
}
