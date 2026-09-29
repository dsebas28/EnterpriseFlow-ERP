<?php

namespace App\Providers;

use App\Support\Tenancy\TenantContext;
use Illuminate\Support\ServiceProvider;

class TenancyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Scoped: flushed between requests and between queued jobs, so a
        // long-running worker never leaks one tenant's context into the next.
        $this->app->scoped(TenantContext::class);
    }
}
