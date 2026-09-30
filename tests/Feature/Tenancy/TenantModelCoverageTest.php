<?php

use App\Support\Tenancy\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/*
| The tenant scope only protects models that opt in. This test makes the
| opt-in mandatory: any model whose table has a company_id column must carry
| the fail-closed CompanyScope (normally through BelongsToCompany), unless it
| is listed below with the reason it is scoped another way.
*/

const TENANT_SCOPE_EXCEPTIONS = [
    // Pivot of users and companies, queried per user as well as per company;
    // route binding is restricted to the active company
    // (Membership::resolveRouteBindingQuery), covered by the isolation sweep.
    App\Models\Membership::class,
    // Platform inbox: the company is only known after interpreting the payload.
    App\Models\WebhookEvent::class,
    // Belongs to a user; company_id only says which company it is about.
    App\Models\Notification::class,
];

/**
 * @return list<class-string<Model>>
 */
function applicationModels(): array
{
    return collect(glob(app_path('Models/*.php')))
        ->map(fn (string $file) => 'App\\Models\\'.Str::before(basename($file), '.php'))
        ->filter(fn (string $class) => is_subclass_of($class, Model::class) && ! (new ReflectionClass($class))->isAbstract())
        ->values()
        ->all();
}

/**
 * @param  class-string<Model>  $class
 */
function isTenantScoped(string $class): bool
{
    new $class; // boots the model and registers its global scopes

    return $class::hasGlobalScope(CompanyScope::class);
}

it('scopes every model that stores company data', function () {
    $unscoped = collect(applicationModels())
        ->filter(fn (string $class) => Schema::hasColumn((new $class)->getTable(), 'company_id'))
        ->reject(fn (string $class) => isTenantScoped($class))
        ->reject(fn (string $class) => in_array($class, TENANT_SCOPE_EXCEPTIONS, true))
        ->values()
        ->all();

    expect($unscoped)->toBe([]);
});

it('keeps the exception list honest', function () {
    foreach (TENANT_SCOPE_EXCEPTIONS as $class) {
        expect(Schema::hasColumn((new $class)->getTable(), 'company_id'))->toBeTrue("{$class} has no company_id: remove it from the list")
            ->and(isTenantScoped($class))->toBeFalse("{$class} is already scoped: remove it from the list");
    }
});

it('fails closed: tenant models cannot be queried without an active company', function () {
    $scoped = collect(applicationModels())->filter(fn (string $class) => isTenantScoped($class));

    expect($scoped)->not->toBeEmpty();

    foreach ($scoped as $class) {
        expect(fn () => $class::query()->count())->toThrow(App\Exceptions\MissingTenantContext::class);
    }
});
