<?php

namespace App\Actions\Webhooks;

use App\Enums\WebhookEventStatus;
use App\Jobs\ProcessWebhookEvent;
use App\Models\WebhookEvent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Stores a verified webhook event exactly once and queues its processing.
 *
 * Duplicates are detected by the unique (provider, external_id) index, so
 * two concurrent deliveries of the same event cannot both be stored. A
 * redelivery of an event that previously failed gets another chance.
 */
final class ReceiveWebhookEvent
{
    public const ACCEPTED = 'accepted';

    public const DUPLICATE = 'duplicate';

    public const REQUEUED = 'requeued';

    /**
     * @param  array{id: string, type: string}&array<string, mixed>  $payload
     * @return array{event: WebhookEvent, outcome: self::ACCEPTED|self::DUPLICATE|self::REQUEUED}
     */
    public function handle(string $provider, array $payload): array
    {
        $event = new WebhookEvent;
        $event->forceFill([
            'provider' => $provider,
            'external_id' => $payload['id'],
            'type' => $payload['type'],
            'payload' => $payload,
            'status' => WebhookEventStatus::Pending,
            'received_at' => now(),
        ]);

        try {
            // Own transaction (a savepoint if one is already open): on
            // PostgreSQL a failed statement aborts the enclosing transaction,
            // which would break the duplicate lookup below.
            DB::transaction(fn () => $event->save());
        } catch (UniqueConstraintViolationException) {
            return $this->redelivery($provider, $payload['id']);
        }

        ProcessWebhookEvent::dispatch($event->id);

        return ['event' => $event, 'outcome' => self::ACCEPTED];
    }

    /**
     * @return array{event: WebhookEvent, outcome: self::DUPLICATE|self::REQUEUED}
     */
    private function redelivery(string $provider, string $externalId): array
    {
        $event = WebhookEvent::where('provider', $provider)->where('external_id', $externalId)->firstOrFail();

        if ($event->status !== WebhookEventStatus::Failed) {
            return ['event' => $event, 'outcome' => self::DUPLICATE];
        }

        $event->forceFill(['status' => WebhookEventStatus::Pending])->save();
        ProcessWebhookEvent::dispatch($event->id);

        return ['event' => $event, 'outcome' => self::REQUEUED];
    }
}
