<?php

namespace App\Support\Logging;

use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Writes security-relevant events to the dedicated `security` log channel
 * with a consistent context (actor, tenant, origin), so they can be shipped
 * to a SIEM and retained independently from application logs.
 */
class SecurityLogger
{
    public function __construct(
        private readonly Request $request,
        private readonly TenantContext $tenant,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function info(string $event, array $context = []): void
    {
        Log::channel('security')->info($event, $this->context($context));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function warning(string $event, array $context = []): void
    {
        Log::channel('security')->warning($event, $this->context($context));
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function context(array $context): array
    {
        return [
            'user_id' => $this->request->user()?->getAuthIdentifier(),
            'company_id' => $this->tenant->id(),
            'ip' => $this->request->ip(),
            'user_agent' => $this->request->userAgent(),
            ...$context,
        ];
    }
}
