<?php

namespace App\Jobs;

use App\Enums\WebhookEventStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Company;
use App\Models\WebhookEvent;
use App\Notifications\SystemAlert;
use App\Services\Notifications\Notifier;
use App\Webhooks\Contracts\WebhookHandler;
use App\Webhooks\UnprocessableWebhook;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Applies a stored webhook event exactly once.
 *
 * The event row is locked and its effects are committed in the same
 * transaction that marks it processed: a redelivered job, a concurrent
 * worker or a manual retry finds it finished and does nothing.
 */
class ProcessWebhookEvent implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [10, 60, 300, 900];

    public int $timeout = 60;

    public function __construct(public readonly string $eventId)
    {
        $this->onQueue('webhooks');
    }

    public function handle(): void
    {
        $event = WebhookEvent::find($this->eventId);

        if ($event === null || $event->status->isFinal()) {
            return;
        }

        // Outside the transaction so the attempt is recorded even on failure.
        $event->forceFill(['status' => WebhookEventStatus::Processing, 'attempts' => $event->attempts + 1])->save();

        try {
            DB::transaction(function (): void {
                $event = WebhookEvent::lockForUpdate()->findOrFail($this->eventId);

                if ($event->status->isFinal()) {
                    return;
                }

                $status = $this->handler($event)->handle($event);

                $event->forceFill([
                    'status' => $status,
                    'processed_at' => now(),
                    'last_error' => null,
                ])->save();
            });
        } catch (UnprocessableWebhook|BusinessRuleViolation $e) {
            // Retrying cannot help: record why and stop.
            $this->markFailed($e);
            Log::channel('queue')->warning('webhook.unprocessable', [
                'event_id' => $this->eventId,
                'error' => $e->getMessage(),
            ]);
        } catch (Throwable $e) {
            WebhookEvent::whereKey($this->eventId)->update([
                'status' => WebhookEventStatus::Pending,
                'last_error' => Str::limit($e->getMessage(), 1000),
            ]);

            throw $e;
        }

        $this->log();
    }

    /**
     * Called by the worker once all attempts are exhausted.
     */
    public function failed(Throwable $e): void
    {
        $this->markFailed($e);
        Log::channel('queue')->error('webhook.failed', ['event_id' => $this->eventId, 'error' => $e->getMessage()]);
    }

    private function handler(WebhookEvent $event): WebhookHandler
    {
        $class = config("webhooks.providers.{$event->provider}.handler");

        if (! is_string($class) || ! is_subclass_of($class, WebhookHandler::class)) {
            throw new UnprocessableWebhook("No handler configured for provider [{$event->provider}].");
        }

        return app($class);
    }

    private function markFailed(Throwable $e): void
    {
        WebhookEvent::whereKey($this->eventId)->update([
            'status' => WebhookEventStatus::Failed,
            'last_error' => Str::limit($e->getMessage(), 1000),
        ]);

        $this->alertCompany($e);
    }

    /**
     * A payment the provider collected but we could not apply needs a
     * human: tell the administrators of the company the event names.
     */
    private function alertCompany(Throwable $e): void
    {
        rescue(function () use ($e): void {
            $event = WebhookEvent::find($this->eventId);
            $companyId = $event?->payload['data']['company_id'] ?? null;
            $company = is_string($companyId) ? Company::find($companyId) : null;

            if ($event === null || $company === null) {
                return;
            }

            app(Notifier::class)->toPermittedMembers(new SystemAlert(
                $company,
                title: "A {$event->provider} event could not be applied",
                body: sprintf('Event %s (%s) failed: %s', $event->external_id, $event->type, Str::limit($e->getMessage(), 200)),
            ));
        }, report: true);
    }

    private function log(): void
    {
        $event = WebhookEvent::find($this->eventId);

        Log::channel('queue')->info('webhook.handled', [
            'event_id' => $this->eventId,
            'provider' => $event?->provider,
            'type' => $event?->type,
            'status' => $event?->status->value,
            'company_id' => $event?->company_id,
        ]);
    }
}
