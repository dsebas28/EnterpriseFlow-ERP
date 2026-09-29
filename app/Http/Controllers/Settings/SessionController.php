<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\Auth\SessionManager;
use App\Support\Logging\SecurityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class SessionController extends Controller
{
    public function __construct(
        private readonly SessionManager $sessions,
        private readonly SecurityLogger $log,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('settings/Sessions', [
            'supported' => $this->sessions->isSupported(),
            'sessions' => $this->sessions->forUser($request->user(), $request->session()->getId()),
        ]);
    }

    public function destroy(Request $request, string $session): RedirectResponse
    {
        abort_if($session === $request->session()->getId(), 422, 'Use log out to end the current session.');
        abort_unless($this->sessions->revoke($request->user(), $session), 404);

        $this->log->info('auth.session_revoked');

        return back()->with('status', 'Session revoked.');
    }

    public function destroyOthers(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);

        // Rotates the remember-me hash too, so "remember me" cookies on other
        // devices stop working, not only their server-side sessions.
        Auth::logoutOtherDevices($request->input('password'));
        $count = $this->sessions->revokeOthers($request->user(), $request->session()->getId());

        $this->log->info('auth.other_sessions_revoked', ['count' => $count]);

        return back()->with('status', 'Other sessions have been logged out.');
    }
}
