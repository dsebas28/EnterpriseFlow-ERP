<?php

use App\Support\Webhooks\WebhookSignature;

const WEBHOOK_NOW = 1_759_140_000;

it('accepts a correctly signed payload', function () {
    $header = WebhookSignature::sign('{"id":"evt_1"}', 'secret', WEBHOOK_NOW);

    expect(WebhookSignature::verify('{"id":"evt_1"}', $header, 'secret', 300, WEBHOOK_NOW))->toBeTrue();
});

it('rejects a tampered payload or a different secret', function () {
    $header = WebhookSignature::sign('{"amount":100}', 'secret', WEBHOOK_NOW);

    expect(WebhookSignature::verify('{"amount":999}', $header, 'secret', 300, WEBHOOK_NOW))->toBeFalse()
        ->and(WebhookSignature::verify('{"amount":100}', $header, 'other', 300, WEBHOOK_NOW))->toBeFalse();
});

it('rejects signatures outside the tolerance window, in both directions', function () {
    $old = WebhookSignature::sign('{}', 'secret', WEBHOOK_NOW - 301);
    $future = WebhookSignature::sign('{}', 'secret', WEBHOOK_NOW + 301);

    expect(WebhookSignature::verify('{}', $old, 'secret', 300, WEBHOOK_NOW))->toBeFalse()
        ->and(WebhookSignature::verify('{}', $future, 'secret', 300, WEBHOOK_NOW))->toBeFalse();
});

it('binds the timestamp into the signature', function () {
    // Re-using a captured v1 with a fresh timestamp must not work.
    $captured = WebhookSignature::sign('{}', 'secret', WEBHOOK_NOW - 3600);
    $v1 = explode('v1=', $captured)[1];

    expect(WebhookSignature::verify('{}', 't='.WEBHOOK_NOW.",v1={$v1}", 'secret', 300, WEBHOOK_NOW))->toBeFalse();
});

it('accepts any of several signatures during secret rotation', function () {
    $new = WebhookSignature::sign('{}', 'new-secret', WEBHOOK_NOW);
    $header = 't='.WEBHOOK_NOW.',v1=deadbeef,'.explode(',', $new)[1];

    expect(WebhookSignature::verify('{}', $header, 'new-secret', 300, WEBHOOK_NOW))->toBeTrue();
});

it('rejects malformed headers and empty secrets', function (?string $header) {
    expect(WebhookSignature::verify('{}', $header, 'secret', 300, WEBHOOK_NOW))->toBeFalse();
})->with([
    'missing' => [null],
    'empty' => [''],
    'no timestamp' => ['v1=abc'],
    'no signature' => ['t='.WEBHOOK_NOW],
    'non-numeric timestamp' => ['t=abc,v1=abc'],
    'garbage' => ['nonsense'],
]);

it('never verifies with an empty secret', function () {
    $header = WebhookSignature::sign('{}', '', WEBHOOK_NOW);

    expect(WebhookSignature::verify('{}', $header, '', 300, WEBHOOK_NOW))->toBeFalse();
});
