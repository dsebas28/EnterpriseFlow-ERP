<?php

namespace App\Listeners;

use App\Models\User;
use App\Support\Logging\SecurityLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;

/**
 * Records authentication activity: last login timestamp and a security
 * log entry for every relevant auth event. Passwords are never logged.
 *
 * Each handleX() method is wired to its event by Laravel's listener
 * auto-discovery (based on the type-hinted event).
 */
class AuthenticationActivityListener
{
    public function __construct(private readonly SecurityLogger $log) {}

    public function handleLogin(Login $event): void
    {
        if ($event->user instanceof User) {
            // Quietly: a login is not a business change worth auditing/touching updated_at.
            $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
        }

        $this->log->info('auth.login', [
            'user_id' => $event->user->getAuthIdentifier(),
            'guard' => $event->guard,
            'remember' => $event->remember,
        ]);
    }

    public function handleFailed(Failed $event): void
    {
        $this->log->warning('auth.failed', [
            'email' => $event->credentials['email'] ?? null,
            'known_user' => $event->user !== null,
            'guard' => $event->guard,
        ]);
    }

    public function handleLockout(Lockout $event): void
    {
        $this->log->warning('auth.lockout', [
            'email' => $event->request->input('email'),
        ]);
    }

    public function handleLogout(Logout $event): void
    {
        $this->log->info('auth.logout', [
            'user_id' => $event->user->getAuthIdentifier(),
        ]);
    }

    public function handlePasswordReset(PasswordReset $event): void
    {
        $this->log->info('auth.password_reset', [
            'user_id' => $event->user->getAuthIdentifier(),
        ]);
    }
}
