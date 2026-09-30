<?php

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Models\Company;
use App\Models\User;

class MemberJoinedNotification extends CompanyNotification
{
    public function __construct(Company $company, User $member)
    {
        parent::__construct(
            $company,
            title: "{$member->name} joined {$company->name}",
            body: "{$member->email} accepted the invitation.",
            url: route('team.members.index'),
        );
    }

    public function category(): NotificationCategory
    {
        return NotificationCategory::MemberJoined;
    }
}
