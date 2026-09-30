<?php

namespace App\Webhooks\Contracts;

use App\Enums\WebhookEventStatus;
use App\Models\WebhookEvent;

/**
 * Interprets the events of one provider.
 *
 * Runs inside the transaction that marks the event as done, so its effects
 * and the "processed" flag commit together. Throw UnprocessableWebhook for
 * events that can never succeed (no retry); any other exception is treated
 * as transient and retried with backoff.
 */
interface WebhookHandler
{
    /**
     * @return WebhookEventStatus::Processed|WebhookEventStatus::Ignored
     */
    public function handle(WebhookEvent $event): WebhookEventStatus;
}
