<?php

namespace App\Http\Controllers\Auth;

use App\Enums\SystemRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Show the login page.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => $request->session()->get('status'),
            // Only in demo mode: never advertise credentials on a real deployment.
            'demo' => config('demo.enabled') ? [
                'password' => config('demo.password'),
                'accounts' => collect((array) config('demo.accounts'))
                    ->map(fn (array $account, string $role) => [
                        'key' => $role,
                        'role' => SystemRole::from($role)->label(),
                        'email' => $account['email'],
                        'summary' => $account['summary'],
                    ])
                    ->values()
                    ->all(),
            ] : null,
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
