<?php

namespace App\Jobs\Middleware;

use App\Models\Company;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Support\Facades\Log;

/**
 * Queue workers have no session, so the tenant a job belongs to is
 * restored here from the company id captured at dispatch time.
 */
final class RestoreTenantContext
{
    public function __construct(private readonly string $companyId) {}

    public function handle(object $job, Closure $next): mixed
    {
        $company = Company::find($this->companyId);

        if ($company === null) {
            // The company is gone; there is nothing meaningful left to do.
            Log::channel('queue')->warning('job.tenant_missing', ['job' => $job::class, 'company_id' => $this->companyId]);

            return null;
        }

        return app(TenantContext::class)->run($company, fn () => $next($job));
    }
}
