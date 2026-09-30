<?php

use App\Enums\SystemRole;
use App\Models\Invitation;
use App\Models\Role;
use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BusinessScenario;

/*
| Sweeps over the *registered* routes rather than hand-picked ones: a route
| added tomorrow is covered automatically, and the explicit lists below are
| the reviewed exceptions. If one of these tests fails after adding a route,
| decide deliberately which list (if any) it belongs to.
*/

/** Reachable without authentication (by name, or URI when unnamed). */
const PUBLIC_ROUTES = [
    'home', 'up', 'storage.local', 'storage.local.upload',
    'health', // readiness probe: up/down and timings only, no details
    'login', 'register', 'password.request', 'password.email', 'password.reset', 'password.store',
    'invitations.show', 'invitations.accept',
    'api.v1.auth.login', 'webhooks.receive',
    // API docs: guarded by Scramble's RestrictedDocsAccess (local env only).
    'scramble.docs.ui', 'scramble.docs.document',
];

/** Tenant routes any active member may use, whatever their roles. */
const OPEN_TO_EVERY_MEMBER = [
    'dashboard', // sections are filtered by permission inside
    'notifications.index', 'notifications.unread-count', 'notifications.read-all',
    'api.v1.notifications.index', 'api.v1.notifications.read-all',
];

/**
 * @return Collection<int, RouteDefinition>
 */
function registeredRoutes(): Collection
{
    return collect(Route::getRoutes()->getRoutes())
        ->reject(fn (RouteDefinition $route) => str_starts_with($route->uri(), '_') || str_starts_with($route->uri(), 'sanctum/'));
}

function routeLabel(RouteDefinition $route): string
{
    return $route->getName() ?? $route->uri();
}

function requiresAuth(RouteDefinition $route): bool
{
    return collect($route->gatherMiddleware())
        ->contains(fn ($m) => is_string($m) && ($m === 'auth' || str_starts_with($m, 'auth:')));
}

function hasMiddleware(RouteDefinition $route, string $name): bool
{
    return in_array($name, $route->gatherMiddleware(), true);
}

function primaryMethod(RouteDefinition $route): string
{
    return collect($route->methods())->reject(fn (string $m) => $m === 'HEAD')->first();
}

/**
 * @param  array<string, string>  $values
 */
function routeUri(RouteDefinition $route, array $values = []): string
{
    return '/'.ltrim(preg_replace_callback('/\{(\w+)\??\}/', fn (array $m) => $values[$m[1]] ?? match ($m[1]) {
        'id' => (string) Str::uuid(),
        'key' => 'sales-by-period',
        default => '01JZZZZZZZZZZZZZZZZZZZZZZZ',
    }, $route->uri()), '/');
}

describe('authentication', function () {
    it('requires authentication on every route except the reviewed public ones', function () {
        $unprotected = registeredRoutes()
            ->reject(fn (RouteDefinition $route) => requiresAuth($route))
            ->map(fn (RouteDefinition $route) => routeLabel($route))
            ->reject(fn (string $label) => in_array($label, PUBLIC_ROUTES, true))
            ->values()
            ->all();

        expect($unprotected)->toBe([]);
    });

    it('turns guests away from every protected route', function () {
        $failures = [];

        foreach (registeredRoutes()->filter(fn (RouteDefinition $route) => requiresAuth($route)) as $route) {
            $api = str_starts_with($route->uri(), 'api/');
            $response = $this->json(primaryMethod($route), routeUri($route), [], $api ? [] : ['Accept' => 'text/html']);

            $ok = $api ? $response->status() === 401 : $response->isRedirect(route('login'));
            if (! $ok) {
                $failures[] = primaryMethod($route).' '.$route->uri().' → '.$response->status();
            }
        }

        expect($failures)->toBe([]);
    });
});

describe('authorization', function () {
    it('denies members without roles on every permission-guarded tenant route', function () {
        [$member, $company] = companyWithMember();
        $failures = [];

        $routes = registeredRoutes()->filter(fn (RouteDefinition $route) => hasMiddleware($route, 'tenant')
            && ! str_contains($route->uri(), '{')
            && ! in_array(routeLabel($route), OPEN_TO_EVERY_MEMBER, true));

        foreach ($routes as $route) {
            $api = str_starts_with($route->uri(), 'api/');
            $api ? Sanctum::actingAs($member) : $this->actingAs($member);

            $response = $this->json(primaryMethod($route), routeUri($route), [], $api ? ['X-Company-Id' => $company->id] : []);

            if ($response->status() !== 403) {
                $failures[] = primaryMethod($route).' '.$route->uri().' → '.$response->status();
            }
        }

        expect($routes)->not->toBeEmpty()
            ->and($failures)->toBe([]);
    });

    it('lets any member reach the routes open to everyone', function () {
        [$member] = companyWithMember();

        $this->actingAs($member)->get(route('dashboard'))->assertOk();
        $this->actingAs($member)->get(route('notifications.index'))->assertOk();
        Sanctum::actingAs($member);
        $this->getJson('/api/v1/notifications')->assertOk();
    });
});

describe('tenant isolation', function () {
    it('answers 404 for records of another company on every model-bound route', function () {
        [$attacker] = memberWithRole(SystemRole::Owner);
        [$victimOwner, $victim] = memberWithRole(SystemRole::Owner);
        $b = BusinessScenario::build($victim, $victimOwner);

        [$victimManager] = memberWithRole(SystemRole::Manager, $victim);
        [$role, $invitation] = tenant()->run($victim, function () use ($victimOwner) {
            $invitation = new Invitation(['email' => 'someone@example.com', 'role_id' => Role::firstWhere('slug', 'sales')->id]);
            $invitation->forceFill(['token_hash' => Invitation::hashToken('x'), 'invited_by' => $victimOwner->id, 'expires_at' => now()->addDay()])->save();

            return [Role::firstWhere('slug', 'manager'), $invitation];
        });

        $models = [
            'product' => $b->chair->id,
            'customer' => $b->customer->id,
            'supplier' => $b->supplier->id,
            'warehouse' => $b->warehouse->id,
            'category' => (string) $b->category->id,
            'sale' => $b->draftSale->id,
            'purchase_order' => $b->draftOrder->id,
            'invoice' => $b->invoice->id,
            'bill' => $b->bill->id,
            'payment' => $b->payment->id,
            'expense' => $b->expense->id,
            'export' => $b->export->id,
            'role' => (string) $role->id,
            'membership' => (string) $victimManager->membershipIn($victim)->id,
            'invitation' => (string) $invitation->id,
        ];

        // Parameters that are not tenant records, each covered by its own tests.
        $notTenantRecords = ['id', 'key', 'token', 'hash', 'provider', 'company', 'session', 'path'];

        $failures = [];
        $unchecked = [];

        $routes = registeredRoutes()->filter(fn (RouteDefinition $route) => hasMiddleware($route, 'tenant') && str_contains($route->uri(), '{'));

        foreach ($routes as $route) {
            $params = $route->parameterNames();
            $first = $params[0];

            if (! array_key_exists($first, $models)) {
                in_array($first, $notTenantRecords, true) || $unchecked[] = $route->uri();

                continue;
            }

            $api = str_starts_with($route->uri(), 'api/');
            $api ? Sanctum::actingAs($attacker) : $this->actingAs($attacker);

            $response = $this->json(primaryMethod($route), routeUri($route, $models));

            if ($response->status() !== 404) {
                $failures[] = primaryMethod($route).' '.$route->uri().' → '.$response->status();
            }
        }

        expect($unchecked)->toBe([])
            ->and($failures)->toBe([]);
    });
});
