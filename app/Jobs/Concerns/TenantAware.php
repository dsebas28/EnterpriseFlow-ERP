<?php

namespace App\Jobs\Concerns;

use App\Jobs\Middleware\RestoreTenantContext;
use App\Models\Company;
use App\Support\Tenancy\TenantContext;

/**
 * For queued jobs that work on tenant data.
 *
 * Captures the active company when the job is created and restores it in
 * the worker. Jobs using this trait must hold *ids*, not Eloquent models:
 * serialized models are re-fetched before job middleware runs, i.e. before
 * the tenant is restored, and the fail-closed tenant scope would reject
 * the query.
 */
trait TenantAware
{
    public string $companyId;

    protected function captureTenant(): void
    {
        $this->companyId = app(TenantContext::class)->idOrFail();
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new RestoreTenantContext($this->companyId)];
    }

    /**
     * Run a callback in the job's tenant outside the middleware pipeline
     * (e.g. from failed(), which the worker calls without middleware).
     */
    protected function inTenant(callable $callback): mixed
    {
        $company = Company::find($this->companyId);

        return $company ? app(TenantContext::class)->run($company, $callback) : null;
    }
}
