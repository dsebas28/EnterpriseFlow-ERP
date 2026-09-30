<?php

namespace App\Console\Commands;

use App\Enums\WebhookEventStatus;
use App\Jobs\ProcessWebhookEvent;
use App\Models\WebhookEvent;
use Illuminate\Console\Command;

class RetryWebhookEvents extends Command
{
    protected $signature = 'webhooks:retry
        {ids?* : Ids of the failed events to retry}
        {--all : Retry every failed event}';

    protected $description = 'Re-queue failed webhook events (processing is idempotent)';

    public function handle(): int
    {
        /** @var list<string> $ids */
        $ids = (array) $this->argument('ids');

        if ($ids === [] && ! $this->option('all')) {
            $this->components->error('Pass event ids or --all.');

            return self::INVALID;
        }

        $events = WebhookEvent::query()
            ->where('status', WebhookEventStatus::Failed)
            ->when($ids !== [], fn ($query) => $query->whereKey($ids))
            ->get();

        foreach ($events as $event) {
            $event->forceFill(['status' => WebhookEventStatus::Pending])->save();
            ProcessWebhookEvent::dispatch($event->id);
        }

        $this->components->info("{$events->count()} webhook events re-queued.");

        return self::SUCCESS;
    }
}
