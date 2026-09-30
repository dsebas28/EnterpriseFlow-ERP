<?php

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;

function verificationUrl(User $user, string $email): string
{
    return URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
        'id' => $user->id,
        'hash' => sha1($email),
    ]);
}

it('renders the verification notice', function () {
    $this->actingAs(User::factory()->unverified()->create())
        ->get('/verify-email')
        ->assertOk();
});

it('verifies the email from the signed link', function () {
    Event::fake();
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get(verificationUrl($user, $user->email))
        ->assertRedirect(route('dashboard', absolute: false).'?verified=1');

    Event::assertDispatched(Verified::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('does not verify with a hash for another email', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get(verificationUrl($user, 'wrong-email'));

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('sends already verified users straight to the dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/verify-email')->assertRedirect(route('dashboard', absolute: false));
    $this->actingAs($user)->get(verificationUrl($user, $user->email))->assertRedirect(route('dashboard', absolute: false).'?verified=1');
});
