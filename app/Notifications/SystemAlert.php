<?php

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Models\Company;

/**
 * Something failed outside a user request and needs a human (integrations).
 */
class SystemAlert extends CompanyNotification
{
    public function __construct(Company $company, string $title, string $body, ?string $url = null)
    {
        parent::__construct($company, $title, $body, $url, 'danger');
    }

    public function category(): NotificationCategory
    {
        return NotificationCategory::SystemAlert;
    }
}
