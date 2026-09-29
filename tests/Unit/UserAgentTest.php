<?php

use App\Support\Http\UserAgent;

it('classifies common user agents', function (string $ua, string $platform, string $browser) {
    expect(UserAgent::parse($ua))->toBe(['platform' => $platform, 'browser' => $browser]);
})->with([
    'chrome windows' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36', 'Windows', 'Chrome'],
    'edge windows' => ['Mozilla/5.0 (Windows NT 10.0) AppleWebKit/537.36 Chrome/130.0 Safari/537.36 Edg/130.0', 'Windows', 'Edge'],
    'safari iphone' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Version/17.0 Mobile Safari/604.1', 'iOS', 'Safari'],
    'firefox linux' => ['Mozilla/5.0 (X11; Linux x86_64; rv:131.0) Gecko/20100101 Firefox/131.0', 'Linux', 'Firefox'],
    'unknown' => ['curl/8.0', 'Unknown', 'Unknown'],
    'null' => ['', 'Unknown', 'Unknown'],
]);
