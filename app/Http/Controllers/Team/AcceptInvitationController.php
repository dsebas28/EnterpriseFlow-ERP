<?php

namespace App\Http\Controllers\Team;

use App\Actions\Auth\CreateUser;
use App\Actions\Team\AcceptInvitation;
use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolveCurrentCompany;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Invitee-facing flow. Reachable without a company context and, for new
 * users, without an account: possession of the token is the credential.
 */
class AcceptInvitationController extends Controller
{
    public function show(Request $request, string $token): Response
    {
        $invitation = $this->pendingOrFail($token);

        return Inertia::render('invitations/Accept', [
            'token' => $token,
            'invitation' => [
                'email' => $invitation->email,
                'company' => $invitation->company->name,
                'role' => $invitation->role->name,
                'expires_at' => $invitation->expires_at->toIso8601String(),
            ],
            'hasAccount' => $this->accountExists($invitation->email),
            'authenticatedEmail' => $request->user()?->email,
        ]);
    }

    public function store(Request $request, string $token, AcceptInvitation $accept, CreateUser $createUser): RedirectResponse
    {
        $invitation = $this->pendingOrFail($token);
        $user = $request->user();

        if ($user === null) {
            if ($this->accountExists($invitation->email)) {
                // Existing account: authenticate first, then come back here.
                redirect()->setIntendedUrl(route('invitations.show', $token));

                return to_route('login');
            }

            $validated = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'password' => ['required', 'confirmed', Password::defaults()],
            ]);

            // The emailed token proves ownership of the address.
            $user = $createUser->handle($validated['name'], $invitation->email, $validated['password'], emailVerified: true);
            Auth::login($user);
            $request->session()->regenerate();
        }

        $membership = $accept->handle($invitation, $user);

        $request->session()->put(ResolveCurrentCompany::SESSION_KEY, $membership->company_id);

        return to_route('dashboard')->with('status', "Welcome to {$invitation->company->name}!");
    }

    private function pendingOrFail(string $token): Invitation
    {
        $invitation = Invitation::findByToken($token);

        abort_unless($invitation?->isPending(), 404);

        return $invitation;
    }

    private function accountExists(string $email): bool
    {
        return User::whereRaw('lower(email) = ?', [strtolower($email)])->exists();
    }
}
