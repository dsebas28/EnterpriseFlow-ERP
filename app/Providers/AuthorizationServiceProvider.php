<?php

namespace App\Providers;

use App\Enums\Permission;
use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthorizationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(PermissionResolver::class);
    }

    public function boot(): void
    {
        // Platform super admins bypass tenant permission checks. They still
        // need a membership to *enter* a company (see ResolveCurrentCompany).
        Gate::before(fn (User $user): ?bool => $user->isSuperAdmin() ? true : null);

        // One ability per permission: enables Gate::allows('sales.cancel'),
        // the `can:sales.cancel` route middleware and $user->can(...).
        foreach (Permission::cases() as $permission) {
            Gate::define($permission->value, fn (User $user): bool => $user->hasPermission($permission));
        }
    }
}
