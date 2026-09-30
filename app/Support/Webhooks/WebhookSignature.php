<?php

namespace App\Support\Webhooks;

/**
 * HMAC-SHA256 request signatures: `t=<unix>,v1=<hex>[,v1=<hex>...]`, where
 * each v1 is hmac_sha256("<t>.<raw body>", secret).
 *
 * The timestamp is part of the signed content, so a captured request cannot
 * be replayed outside the tolerance window with a fresh timestamp.
 */
final class WebhookSignature
{
    public static function sign(string $payload, string $secret, int $timestamp): string
    {
        return 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$payload, $secret);
    }

    public static function verify(string $payload, ?string $header, string $secret, int $tolerance, ?int $now = null): bool
    {
        if ($header === null || $header === '' || $secret === '') {
            return false;
        }

        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');

            if ($key === 't' && ctype_digit($value)) {
                $timestamp = (int) $value;
            } elseif ($key === 'v1' && $value !== '') {
                $signatures[] = $value;
            }
        }

        if ($timestamp === null || $signatures === []) {
            return false;
        }

        if (abs(($now ?? now()->getTimestamp()) - $timestamp) > $tolerance) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        foreach ($signatures as $signature) {
            // Constant-time comparison: no timing oracle on the secret.
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }
}
