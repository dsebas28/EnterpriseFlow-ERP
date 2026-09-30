<?php

use App\Enums\SystemRole;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\PersonalAccessToken;

beforeEach(function () {
    $this->user = User::factory()->create(['email' => 'ana@example.com']);
    [, $this->company] = memberWithRole(SystemRole::Sales, user: $this->user);
});

function apiLogin(object $test, array $overrides = []): TestResponse
{
    return $test->postJson('/api/v1/auth/login', [
        'email' => 'ana@example.com',
        'password' => 'password',
        'device_name' => 'pest',
        ...$overrides,
    ]);
}

it('issues a bearer token and lists the companies of the user', function () {
    $response = apiLogin($this)
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Logged in.')
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonPath('data.user.email', 'ana@example.com')
        ->assertJsonPath('data.companies.0.id', $this->company->id);

    $token = $response->json('data.token');
    expect($token)->toBeString()
        ->and(PersonalAccessToken::count())->toBe(1)
        // Only the hash is stored, never the plain token.
        ->and(PersonalAccessToken::first()->token)->not->toBe(explode('|', $token)[1])
        ->and(PersonalAccessToken::first()->expires_at)->not->toBeNull();

    $this->withToken($token)->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.email', 'ana@example.com');
});

it('rejects bad credentials and inactive accounts with the same validation error', function () {
    apiLogin($this, ['password' => 'wrong'])
        ->assertUnprocessable()
        ->assertExactJson([
            'success' => false,
            'message' => 'Validation failed.',
            'errors' => ['email' => [trans('auth.failed')]],
        ]);

    User::factory()->inactive()->create(['email' => 'off@example.com']);
    apiLogin($this, ['email' => 'off@example.com'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.email.0', trans('auth.failed'));

    expect(PersonalAccessToken::count())->toBe(0);
});

it('requires a device name', function () {
    apiLogin($this, ['device_name' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('device_name');
});

it('throttles repeated login attempts', function () {
    foreach (range(1, 5) as $attempt) {
        apiLogin($this, ['password' => 'wrong'])->assertUnprocessable();
    }

    apiLogin($this)
        ->assertTooManyRequests()
        ->assertJsonPath('success', false)
        ->assertHeader('Retry-After');
});

it('revokes the current token on logout', function () {
    $token = apiLogin($this)->json('data.token');

    $this->withToken($token)->postJson('/api/v1/auth/logout')
        ->assertOk()
        ->assertJsonPath('message', 'Logged out.');

    expect(PersonalAccessToken::count())->toBe(0);

    // Guards cache the resolved user between requests in a test.
    app('auth')->forgetGuards();
    $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
});

it('answers unauthenticated requests with the JSON error envelope', function () {
    $this->get('/api/v1/products')
        ->assertUnauthorized()
        ->assertExactJson(['success' => false, 'message' => 'Unauthenticated.', 'errors' => []]);
});

it('rejects tokens of deactivated users', function () {
    $token = apiLogin($this)->json('data.token');
    $this->user->forceFill(['status' => 'inactive'])->save();

    $this->withToken($token)->getJson('/api/v1/me')
        ->assertForbidden()
        ->assertJsonPath('success', false);
});

it('returns 404 in the envelope for unknown endpoints', function () {
    $this->getJson('/api/v1/nope')
        ->assertNotFound()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Resource not found.');
});
