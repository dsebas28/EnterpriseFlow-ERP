<?php

use App\Enums\SystemRole;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('renders the landing and login pages', function () {
    $this->get('/')->assertOk();
    $this->get('/login')->assertOk();
});

it('never lists demo accounts outside demo mode', function () {
    config(['demo.enabled' => false]);

    $this->get('/login')->assertInertia(fn (Assert $page) => $page->component('auth/Login')->where('demo', null));
    $this->get('/')->assertInertia(fn (Assert $page) => $page->where('demo', false));
});

it('lists one demo account per role in demo mode', function () {
    config(['demo.enabled' => true]);

    $this->get('/login')->assertInertia(fn (Assert $page) => $page
        ->where('demo.password', 'password')
        ->has('demo.accounts', count(SystemRole::cases()))
        ->where('demo.accounts.0', [
            'role' => 'Company Owner',
            'email' => 'owner@demo.test',
            'summary' => 'Everything, in both demo companies',
        ]));
});

it('authenticates with valid credentials', function () {
    $user = User::factory()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
});

it('rejects an invalid password', function () {
    $user = User::factory()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('locks the login out after five failed attempts per email and IP', function () {
    $this->freezeTime();
    $user = User::factory()->create();

    foreach (range(1, 5) as $attempt) {
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);
    }

    // Even the right password is refused while locked out.
    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors(['email' => __('auth.throttle', ['seconds' => 60, 'minutes' => 1])]);

    $this->assertGuest();
});

it('logs out', function () {
    $this->actingAs(User::factory()->create())
        ->post('/logout')
        ->assertRedirect('/');

    $this->assertGuest();
});
