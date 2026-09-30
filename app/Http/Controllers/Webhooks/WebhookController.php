<?php

namespace App\Http\Controllers\Webhooks;

use App\Actions\Webhooks\ReceiveWebhookEvent;
use App\Http\Controllers\Controller;
use App\Support\Api\ApiResponse;
use App\Support\Logging\SecurityLogger;
use App\Support\Webhooks\WebhookSignature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Inbound webhook receiver: verify, persist, acknowledge, process later.
 *
 * The response is sent as soon as the event is stored, so slow processing
 * never makes the provider time out and redeliver.
 */
class WebhookController extends Controller
{
    public function __construct(private readonly SecurityLogger $log) {}

    /**
     * Receive a provider event.
     *
     * @unauthenticated
     */
    public function __invoke(Request $request, string $provider, ReceiveWebhookEvent $receive): JsonResponse
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

        /** @var array{id: string, type: string}&array<string, mixed> $body */
        ['event' => $event, 'outcome' => $outcome] = $receive->handle($provider, $body);

        return match ($outcome) {
            ReceiveWebhookEvent::ACCEPTED => ApiResponse::success(['id' => $event->id, 'duplicate' => false], 'Event accepted.', 202),
            ReceiveWebhookEvent::REQUEUED => ApiResponse::success(['id' => $event->id, 'duplicate' => true], 'Event re-queued.', 202),
            ReceiveWebhookEvent::DUPLICATE => ApiResponse::success(['id' => $event->id, 'duplicate' => true], 'Event already received.'),
        };
    }
}
