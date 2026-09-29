<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the active company for the authenticated user and stores it in
 * the TenantContext. Membership is re-validated on every request, so a
 * suspended member loses access immediately, not at next login.
 *
 * - Web (session): company chosen via the switcher, stored in the session.
 * - API (stateless): company sent in the X-Company-Id header.
 *
 * Runs before SubstituteBindings (see bootstrap/app.php) so that
 * route-model binding is already tenant-scoped.
 */
class ResolveCurrentCompany
{
    public const SESSION_KEY = 'current_company_id';

    public const HEADER = 'X-Company-Id';

    public function __construct(private readonly TenantContext $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var User $user */
        $user = $request->user();

        $stateful = $request->hasSession();
        $requestedId = $stateful
            ? $request->session()->get(self::SESSION_KEY)
            : $request->header(self::HEADER);

        $requestedId = is_string($requestedId) ? $requestedId : null;
        $company = $this->resolve($user, $requestedId, fallback: $stateful);

        if ($requestedId !== null && $company?->getKey() !== $requestedId) {
            Log::channel('security')->warning('tenant.access_denied', [
                'user_id' => $user->getKey(),
                'requested_company_id' => $requestedId,
                'ip' => $request->ip(),
            ]);

            // An explicit API header for a foreign company is an error; a stale
            // session value (e.g. the user was removed) just falls back.
            if (! $stateful) {
                return $this->deny();
            }
        }

        if ($company === null) {
            return $stateful
                ? redirect()->route('onboarding.company.create')
                : $this->deny();
        }

        if ($stateful) {
            $request->session()->put(self::SESSION_KEY, $company->getKey());
        }

        $this->tenant->set($company);

        return $next($request);
    }

    /**
     * The requested company if the user may operate in it; otherwise the
     * user's default company when falling back is allowed.
     */
    private function resolve(User $user, ?string $requestedId, bool $fallback): ?Company
    {
        if ($requestedId !== null) {
            $company = $user->activeCompanies()->whereKey($requestedId)->first();

            if ($company !== null || ! $fallback) {
                return $company;
            }
        }

        return $user->activeCompanies()->first();
    }

    private function deny(): Response
    {
        return response()->json([
            'success' => false,
            'message' => 'You do not have access to the requested company.',
        ], Response::HTTP_FORBIDDEN);
    }
}
