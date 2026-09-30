<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->user = User::factory()->create();
});

it('renders the forgot password page', function () {
    $this->get('/forgot-password')->assertOk();
});

it('emails a reset link', function () {
    $this->post('/forgot-password', ['email' => $this->user->email]);

    Notification::assertSentTo($this->user, ResetPassword::class);
});

it('renders the reset page from the emailed token', function () {
    $this->post('/forgot-password', ['email' => $this->user->email]);

    Notification::assertSentTo($this->user, ResetPassword::class, function (ResetPassword $notification) {
        $this->get('/reset-password/'.$notification->token)->assertOk();

        return true;
    });
});

it('resets the password with a valid token', function () {
    $this->post('/forgot-password', ['email' => $this->user->email]);

    Notification::assertSentTo($this->user, ResetPassword::class, function (ResetPassword $notification) {
        $this->post('/reset-password', [
            'token' => $notification->token,
            'email' => $this->user->email,
            'password' => 'new-Secret-123',
            'password_confirmation' => 'new-Secret-123',
        ])->assertSessionHasNoErrors()->assertRedirect(route('login'));

        return true;
    });

    expect(Hash::check('new-Secret-123', $this->user->fresh()->password))->toBeTrue();
});

it('rejects an invalid token', function () {
    $this->post('/reset-password', [
        'token' => 'forged',
        'email' => $this->user->email,
        'password' => 'new-Secret-123',
        'password_confirmation' => 'new-Secret-123',
    ])->assertSessionHasErrors('email');

    expect(Hash::check('password', $this->user->fresh()->password))->toBeTrue();
});
