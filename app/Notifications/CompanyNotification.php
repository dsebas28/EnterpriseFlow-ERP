<?php

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Models\Company;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Base for notifications about something that happened in a company.
 *
 * Built from a snapshot of plain values when the event happens: queue
 * workers have no tenant context (tenant models could not be re-fetched),
 * and the message must describe the facts as they were. Delivered on the
 * channels the recipient chose for the category.
 */
abstract class CompanyNotification extends Notification implements ShouldQueue
{
    use Queueable;

    // Not readonly: queued notifications are rehydrated by reflection from
    // subclasses, which cannot initialise a parent's readonly properties.
    public string $companyId;

    public string $companyName;

    /**
     * @param  'info'|'success'|'warning'|'danger'  $level
     */
    public function __construct(
        Company $company,
        public string $title,
        public string $body,
        public ?string $url = null,
        public string $level = 'info',
    ) {
        $this->companyId = $company->id;
        $this->companyName = $company->name;

        $this->onQueue('notifications');
    }

    abstract public function category(): NotificationCategory;

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof User
            ? $notifiable->notificationChannels($this->category())
            : ['mail'];
    }

    /**
     * Stored in the `type` column: a stable name instead of the class name.
     */
    public function databaseType(object $notifiable): string
    {
        return $this->category()->value;
    }

    /**
     * @return array{category: string, title: string, body: string, url: string|null, level: string, company_name: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'category' => $this->category()->value,
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
            'level' => $this->level,
            'company_name' => $this->companyName,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("[{$this->companyName}] {$this->title}")
            ->greeting($this->title)
            ->line($this->body);

        if ($this->level === 'danger') {
            $mail->error();
        }

        if ($this->url !== null) {
            $mail->action('View in '.config('app.name'), $this->url);
        }

        return $mail->line('You can choose which emails you receive in Settings → Notifications.');
    }
}
