<?php

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * Invitation email. Carries a snapshot of plain values instead of the
 * Invitation model: queue workers run without a tenant context, and the
 * email must describe the invitation as it was when it was sent.
 */
class CompanyInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    public readonly string $companyName;

    public readonly ?string $inviterName;

    public readonly Carbon $expiresAt;

    public readonly string $url;

    /**
     * @param  string  $token  Plain token; only its hash is stored in the database.
     */
    public function __construct(Invitation $invitation, string $token)
    {
        $this->companyName = (string) $invitation->company()->value('name');
        $this->inviterName = $invitation->inviter?->name;
        $this->expiresAt = $invitation->expires_at;
        $this->url = route('invitations.show', $token);

        $this->onQueue('notifications');
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        // Existing users also see it in their bell, as a personal
        // notification (not tied to a company they are not part of yet).
        return $notifiable instanceof User ? ['mail', 'database'] : ['mail'];
    }

    public function databaseType(object $notifiable): string
    {
        return NotificationCategory::Invitation->value;
    }

    /**
     * @return array{category: string, title: string, body: string, url: string, level: string, company_name: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'category' => NotificationCategory::Invitation->value,
            'title' => "You're invited to join {$this->companyName}",
            'body' => ($this->inviterName ? "{$this->inviterName} invited you." : 'You have been invited.')
                .' The invitation expires on '.$this->expiresAt->toFormattedDayDateString().'.',
            'url' => $this->url,
            'level' => 'info',
            'company_name' => $this->companyName,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $who = $this->inviterName ? "{$this->inviterName} has" : 'You have been';

        return (new MailMessage)
            ->subject("You're invited to join {$this->companyName} on ".config('app.name'))
            ->greeting('Hello!')
            ->line("{$who} invited you to join **{$this->companyName}**.")
            ->action('Accept invitation', $this->url)
            ->line('This invitation expires on '.$this->expiresAt->toFormattedDayDateString().'.')
            ->line('If you were not expecting it, you can ignore this email.');
    }
}
