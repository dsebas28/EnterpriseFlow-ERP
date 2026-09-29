<?php

use App\Enums\UserStatus;
use App\Models\User;
use App\Support\Logging\SecurityLogger;
use Laravel\Sanctum\Sanctum;

it('rejects login for inactive accounts with the generic credentials error', function () {
    $user = User::factory()->inactive()->create();

    $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors(['email' => trans('auth.failed')]);

    $this->assertGuest();
});

it('logs out a user deactivated in the middle of a session', function () {
    [$user] = companyWithMember();

    $this->actingAs($user)->get(route('dashboard'))->assertOk();

    $user->forceFill(['status' => UserStatus::Inactive])->save();

    $this->get(route('dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});

it('blocks API tokens of deactivated users', function () {
    $user = User::factory()->inactive()->create();
    Sanctum::actingAs($user);

    Route::middleware(['api', 'auth:sanctum', 'active'])->get('/api/_test/ping', fn () => 'pong');

    $this->getJson('/api/_test/ping')->assertForbidden()->assertJson(['success' => false]);
});

it('records the last login time', function () {
    $user = User::factory()->create(['last_login_at' => null]);

    $this->post(route('login'), ['email' => $user->email, 'password' => 'password']);

    expect($user->fresh()->last_login_at)->not->toBeNull();
});

it('writes failed logins to the security log without the password', function () {
    $log = $this->spy(SecurityLogger::class);
    $user = User::factory()->create();

    $this->post(route('login'), ['email' => $user->email, 'password' => 'wrong-password']);

    $log->shouldHaveReceived('warning')
        ->with('auth.failed', Mockery::on(fn (array $context) => $context['email'] === $user->email
            && $context['known_user'] === true
            && ! in_array('wrong-password', $context, true)))
        ->once();
});

it('writes successful logins to the security log', function () {
    $log = $this->spy(SecurityLogger::class);
    $user = User::factory()->create();

    $this->post(route('login'), ['email' => $user->email, 'password' => 'password']);

    $log->shouldHaveReceived('info')->with('auth.login', Mockery::subset(['user_id' => $user->id]))->once();
});
