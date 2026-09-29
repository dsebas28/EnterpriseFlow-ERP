<?php

namespace App\Support\Http;

/**
 * Deliberately small user-agent classifier for display purposes only
 * ("Chrome on Windows"). Never used for security decisions.
 */
final class UserAgent
{
    /**
     * @return array{platform: string, browser: string}
     */
    public static function parse(?string $userAgent): array
    {
        $ua = (string) $userAgent;

        return [
            'platform' => self::match($ua, [
                'Windows' => 'Windows',
                'iPhone' => 'iOS',
                'iPad' => 'iOS',
                'Android' => 'Android',
                'Mac OS X' => 'macOS',
                'Linux' => 'Linux',
            ]),
            // Order matters: Edge and Opera also advertise "Chrome", Chrome advertises "Safari".
            'browser' => self::match($ua, [
                'Edg/' => 'Edge',
                'OPR/' => 'Opera',
                'Firefox/' => 'Firefox',
                'Chrome/' => 'Chrome',
                'Safari/' => 'Safari',
            ]),
        ];
    }

    /**
     * @param  array<string, string>  $needles
     */
    private static function match(string $haystack, array $needles): string
    {
        foreach ($needles as $needle => $label) {
            if (str_contains($haystack, $needle)) {
                return $label;
            }
        }

        return 'Unknown';
    }
}
