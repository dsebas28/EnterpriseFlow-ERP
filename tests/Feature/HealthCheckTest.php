<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

it('reports every dependency up', function () {
    $this->getJson('/health')
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('status', 'ok')
        ->assertJsonPath('checks.database.status', 'up')
        ->assertJsonPath('checks.cache.status', 'up')
        ->assertJsonPath('checks.storage.status', 'up')
        ->assertJsonMissingPath('checks.redis') // not used by the test environment
        ->assertJsonStructure(['checks' => ['database' => ['latency_ms']], 'checked_at']);
});

it('answers 503 without leaking details when a dependency is down', function () {
    Log::spy();
    DB::shouldReceive('select')->andThrow(new RuntimeException('SQLSTATE[08006] password authentication failed for user "prod"'));

    $response = $this->getJson('/health')
        ->assertStatus(503)
        ->assertJsonPath('status', 'degraded')
        ->assertJsonPath('checks.database.status', 'down')
        ->assertJsonPath('checks.cache.status', 'up');

    expect($response->getContent())->not->toContain('SQLSTATE')->not->toContain('password');
    Log::shouldHaveReceived('error')->withArgs(fn (string $message, array $context) => $message === 'health.check_failed' && $context['check'] === 'database');
});

it('checks redis when the app depends on it', function () {
    config(['cache.default' => 'redis', 'redis.default.host' => '127.0.0.1', 'redis.default.port' => 1]);

    $this->getJson('/health')
        ->assertStatus(503)
        ->assertJsonPath('checks.redis.status', 'down');
});
