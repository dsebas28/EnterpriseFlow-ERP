<?php

namespace App\Http\Controllers\Webhooks;

use App\Enums\WebhookEventStatus;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessWebhookEvent;
use App\Models\WebhookEvent;
use App\Support\Api\ApiResponse;
use App\Support\Logging\SecurityLogger;
use App\Support\Webhooks\WebhookSignature;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Inbound webhook receiver: verify, persist, acknowledge, process later.
 *
 * The response is sent as soon as the event is stored, so slow processing
 * never makes the provider time out and redeliver. Duplicates are detected
 * by the unique (provider, external_id) index.
 */
class WebhookController extends Controller
{
    public function __construct(private readonly SecurityLogger $log) {}

    /**
     * Receive a provider event.
     *
     * @unauthenticated
     */
    public function __invoke(Request $request, string $provider): JsonResponse
    {
        $secret = config("webhooks.providers.{$provider}.secret");

        if (! is_string($secret) || $secret === '') {
            return ApiResponse::error('Unknown webhook provider.', 404);
        }

        $payload = $request->getContent();
        $signature = $request->header((string) config('webhooks.signature_header'));

        if (! WebhookSignature::verify($payload, is_string($signature) ? $signature : null, $secret, (int) config('webhooks.tolerance'))) {
            $this->log->warning('webhook.invalid_signature', ['provider' => $provider]);

            return ApiResponse::error('Invalid signature.', 401);
        }

        $body = json_decode($payload, true);
        $validator = Validator::make(is_array($body) ? $body : [], [
            'id' => ['required', 'string', 'max:191'],
            'type' => ['required', 'string', 'max:100'],
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Invalid payload.', 422, $validator->errors()->toArray());
        }

        /** @var array<string, mixed> $body */
        try {
            $event = new WebhookEvent;
            $event->forceFill([
                'provider' => $provider,
                'external_id' => $body['id'],
                'type' => $body['type'],
                'payload' => $body,
                'status' => WebhookEventStatus::Pending,
                'received_at' => now(),
            ])->save();
        } catch (UniqueConstraintViolationException) {
            return $this->duplicate($provider, (string) $body['id']);
        }

        ProcessWebhookEvent::dispatch($event->id);

        return ApiResponse::success(['id' => $event->id, 'duplicate' => false], 'Event accepted.', 202);
    }

    /**
     * A redelivery. Acknowledged without side effects, except that an event
     * that previously failed gets another chance.
     */
    private function duplicate(string $provider, string $externalId): JsonResponse
    {
        $event = WebhookEvent::where('provider', $provider)->where('external_id', $externalId)->firstOrFail();

        if ($event->status === WebhookEventStatus::Failed) {
            $event->forceFill(['status' => WebhookEventStatus::Pending])->save();
            ProcessWebhookEvent::dispatch($event->id);

            return ApiResponse::success(['id' => $event->id, 'duplicate' => true], 'Event re-queued.', 202);
        }

        return ApiResponse::success(['id' => $event->id, 'duplicate' => true], 'Event already received.');
    }
}
