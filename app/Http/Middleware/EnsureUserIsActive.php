<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Api\ApiResponse;
use App\Support\Logging\SecurityLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Terminates access for accounts deactivated after they authenticated.
 * Without this, a disabled user would keep working until their session
 * or token expired.
 */
class EnsureUserIsActive
{
    public function __construct(private readonly SecurityLogger $log) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || $user->isActive()) {
            return $next($request);
        }

        $this->log->warning('auth.inactive_user_blocked', ['user_id' => $user->id]);

        if ($request->hasSession()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('status', 'Your account has been deactivated.');
        }

        return ApiResponse::error('This account has been deactivated.', Response::HTTP_FORBIDDEN);
    }
}
