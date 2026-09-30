<?php

namespace App\Notifications\Channels;

use App\Notifications\CompanyNotification;
use Illuminate\Notifications\Channels\DatabaseChannel;
use Illuminate\Notifications\Notification;

/**
 * The database channel, plus the company the notification is about in its
 * own indexed column (bound in place of Laravel's DatabaseChannel).
 */
class TenantDatabaseChannel extends DatabaseChannel
{
    /**
     * @return array<string, mixed>
     */
    protected function buildPayload($notifiable, Notification $notification): array
    {
        return [
            ...parent::buildPayload($notifiable, $notification),
            'company_id' => $notification instanceof CompanyNotification ? $notification->companyId : null,
        ];
    }
}
