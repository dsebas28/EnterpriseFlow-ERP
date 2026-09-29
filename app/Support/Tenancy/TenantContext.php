<?php

namespace App\Support\Tenancy;

use App\Exceptions\MissingTenantContext;
use App\Models\Company;

/**
 * Holds the company the current request, job or command operates on.
 *
 * Registered as a *scoped* binding, so it is reset between requests and
 * between queued jobs handled by the same worker process. This is the
 * single place that knows the active tenant: switching to a
 * database-per-tenant model would hook the connection swap into set().
 */
final class TenantContext
{
    private ?Company $company = null;

    private bool $bypassed = false;

    public function set(?Company $company): void
    {
        $this->company = $company;
    }

    public function company(): ?Company
    {
        return $this->company;
    }

    public function companyOrFail(): Company
    {
        return $this->company ?? throw new MissingTenantContext;
    }

    public function id(): ?string
    {
        return $this->company?->getKey();
    }

    public function idOrFail(): string
    {
        return $this->companyOrFail()->getKey();
    }

    public function check(): bool
    {
        return $this->company !== null;
    }

    public function isBypassed(): bool
    {
        return $this->bypassed;
    }

    /**
     * Run a callback as the given company, restoring the previous context after.
     *
     * @template T
     *
     * @param  callable(Company): T  $callback
     * @return T
     */
    public function run(Company $company, callable $callback): mixed
    {
        $previous = $this->company;
        $this->company = $company;

        try {
            return $callback($company);
        } finally {
            $this->company = $previous;
        }
    }

    /**
     * Run a callback with tenant scoping disabled. Reserved for platform-level
     * code (super admin tooling, maintenance commands); never call it from
     * a code path reachable by a regular tenant user.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function withoutTenancy(callable $callback): mixed
    {
        $previous = $this->bypassed;
        $this->bypassed = true;

        try {
            return $callback();
        } finally {
            $this->bypassed = $previous;
        }
    }
}
