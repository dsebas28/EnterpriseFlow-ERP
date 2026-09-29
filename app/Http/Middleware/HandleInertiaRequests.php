<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        return array_merge(parent::share($request), [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $request->user(),
                // UI hints only (show/hide actions); every action is re-authorised server-side.
                'permissions' => fn (): array => $request->user()?->currentPermissions() ?? [],
            ],
            // Closures are resolved at render time, i.e. after the tenant
            // middleware has populated the TenantContext.
            'tenant' => fn (): ?array => $request->user() ? [
                'current' => $this->currentCompany(),
                'companies' => $request->user()->activeCompanies()
                    ->get(['companies.id', 'companies.name'])
                    ->map(fn (Company $company): array => [
                        'id' => $company->id,
                        'name' => $company->name,
                    ])
                    ->all(),
            ] : null,
        ]);
    }

    /**
     * @return array{id: string, name: string, currency: string, timezone: string}|null
     */
    private function currentCompany(): ?array
    {
        $company = app(TenantContext::class)->company();

        return $company ? [
            'id' => $company->id,
            'name' => $company->name,
            'currency' => $company->currency,
            'timezone' => $company->timezone,
        ] : null;
    }
}
