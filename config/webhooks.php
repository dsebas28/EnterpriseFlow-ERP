<?php

use App\Webhooks\Handlers\PaymentGatewayHandler;

return [

    /*
    |--------------------------------------------------------------------------
    | Inbound webhooks
    |--------------------------------------------------------------------------
    |
    | Each provider posts to POST /api/webhooks/{provider}. Requests must be
    | signed with the provider secret (header `X-Webhook-Signature`,
    | `t=<unix>,v1=<hex hmac-sha256 of "<t>.<raw body>">`). Several v1 values
    | may be sent while a secret is being rotated.
    |
    | A provider without a secret is disabled (404): nothing is accepted
    | unsigned.
    |
    */

    'signature_header' => 'X-Webhook-Signature',

    // Maximum age (seconds) of a signed request; blocks replayed captures.
    'tolerance' => (int) env('WEBHOOK_TOLERANCE', 300),

    // Processed and ignored events are kept this long for auditing.
    'retention_days' => (int) env('WEBHOOK_RETENTION_DAYS', 90),

    'providers' => [
        'payments' => [
            'secret' => env('PAYMENTS_WEBHOOK_SECRET'),
            'handler' => PaymentGatewayHandler::class,
        ],
    ],

];
