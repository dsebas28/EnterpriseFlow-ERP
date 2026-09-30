<?php

use App\Enums\SystemRole;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Route::middleware('api')->get('api/v1/_test/boom', fn () => throw new RuntimeException('SQLSTATE secret connection detail'));
    [$this->user] = memberWithRole(SystemRole::Owner);
    Sanctum::actingAs($this->user);
});

it('hides unexpected errors behind a generic message and logs them', function () {
    config(['app.debug' => false]);
    Log::spy();

    $this->getJson('/api/v1/_test/boom')
        ->assertStatus(500)
        ->assertExactJson([
            'success' => false,
            'message' => 'Something went wrong. Please try again later.',
            'errors' => [],
        ]);

    Log::shouldHaveReceived('error')->withArgs(fn (string $message, array $context) => $message === 'api.unhandled_exception'
        && $context['exception'] === RuntimeException::class);
});

it('shows the real message only in debug mode', function () {
    config(['app.debug' => true]);

    $this->getJson('/api/v1/_test/boom')
        ->assertStatus(500)
        ->assertJsonPath('message', 'SQLSTATE secret connection detail');
});

it('keeps the envelope for other HTTP errors', function () {
    $this->postJson('/api/v1/me')
        ->assertStatus(405)
        ->assertJsonPath('success', false)
        ->assertJsonStructure(['success', 'message', 'errors']);
});

it('answers JSON even when the client does not ask for it', function () {
    $this->get('/api/v1/products/01JZZZZZZZZZZZZZZZZZZZZZZZ')
        ->assertNotFound()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonPath('message', 'Resource not found.');
});
