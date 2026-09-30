<?php

namespace App\Events;

use App\Models\Membership;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class MemberJoinedCompany implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly Membership $membership) {}
}
