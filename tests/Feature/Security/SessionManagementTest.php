<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['session.driver' => 'database']);
});

function fakeSession(User $user, string $id, string $agent = 'Mozilla/5.0 (Windows NT 10.0) Chrome/130.0 Safari/537.36'): void
{
    DB::table('sessions')->insert([
        'id' => $id,
        'user_id' => $user->id,
        'ip_address' => '10.0.0.1',
        'user_agent' => $agent,
        'payload' => base64_encode(serialize([])),
        'last_activity' => now()->subHour()->getTimestamp(),
    ]);
}

it('lists only the sessions of the authenticated user', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    fakeSession($user, 'mine-1');
    fakeSession($other, 'theirs-1');

    $this->actingAs($user)
        ->get(route('sessions.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Sessions')
            ->where('supported', true)
            ->where('sessions', fn ($sessions) => collect($sessions)->pluck('id')->all() === ['mine-1'])
            ->where('sessions.0.browser', 'Chrome')
            ->where('sessions.0.platform', 'Windows'));
});

it('revokes one of the user sessions', function () {
    $user = User::factory()->create();
    fakeSession($user, 'old-laptop');

    $this->actingAs($user)->delete(route('sessions.destroy', 'old-laptop'))->assertRedirect();

    $this->assertDatabaseMissing('sessions', ['id' => 'old-laptop']);
});

it('cannot revoke a session belonging to another user', function () {
    $user = User::factory()->create();
    $victim = User::factory()->create();
    fakeSession($victim, 'victim-session');

    $this->actingAs($user)->delete(route('sessions.destroy', 'victim-session'))->assertNotFound();

    $this->assertDatabaseHas('sessions', ['id' => 'victim-session']);
});

it('requires the password to log out other sessions', function () {
    $user = User::factory()->create();
    fakeSession($user, 'phone');

    $this->actingAs($user)
        ->delete(route('sessions.destroy-others'), ['password' => 'wrong'])
        ->assertSessionHasErrors('password');

    $this->assertDatabaseHas('sessions', ['id' => 'phone']);
});

it('logs out every other session', function () {
    $user = User::factory()->create();
    fakeSession($user, 'phone');
    fakeSession($user, 'tablet');

    $this->actingAs($user)
        ->delete(route('sessions.destroy-others'), ['password' => 'password'])
        ->assertSessionHasNoErrors();

    expect(DB::table('sessions')->whereIn('id', ['phone', 'tablet'])->count())->toBe(0);
});

it('ends other sessions when the password is changed', function () {
    $user = User::factory()->create();
    fakeSession($user, 'stolen-session');

    $this->actingAs($user)
        ->put(route('password.update'), [
            'current_password' => 'password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])
        ->assertSessionHasNoErrors();

    $this->assertDatabaseMissing('sessions', ['id' => 'stolen-session']);
});
