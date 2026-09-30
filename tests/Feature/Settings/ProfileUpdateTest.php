<?php

use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('renders the profile page', function () {
    $this->actingAs($this->user)->get('/settings/profile')->assertOk();
});

it('updates the profile and asks to re-verify a changed email', function () {
    $this->actingAs($this->user)
        ->patch('/settings/profile', ['name' => 'Test User', 'email' => 'test@example.com'])
        ->assertSessionHasNoErrors()
        ->assertRedirect('/settings/profile');

    $this->user->refresh();
    expect($this->user->name)->toBe('Test User')
        ->and($this->user->email)->toBe('test@example.com')
        ->and($this->user->email_verified_at)->toBeNull();
});

it('keeps the verification when the email does not change', function () {
    $this->actingAs($this->user)
        ->patch('/settings/profile', ['name' => 'Test User', 'email' => $this->user->email])
        ->assertSessionHasNoErrors()
        ->assertRedirect('/settings/profile');

    expect($this->user->refresh()->email_verified_at)->not->toBeNull();
});

it('deletes the account with the correct password', function () {
    $this->actingAs($this->user)
        ->delete('/settings/profile', ['password' => 'password'])
        ->assertSessionHasNoErrors()
        ->assertRedirect('/');

    $this->assertGuest();
    expect($this->user->fresh())->toBeNull();
});

it('requires the correct password to delete the account', function () {
    $this->actingAs($this->user)
        ->from('/settings/profile')
        ->delete('/settings/profile', ['password' => 'wrong-password'])
        ->assertSessionHasErrors('password')
        ->assertRedirect('/settings/profile');

    expect($this->user->fresh())->not->toBeNull();
});
