<?php

namespace App\Support\Audit;

use App\Models\AuditLog;
use App\Models\Company;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Writes audit entries with their full context (who, which company, from
 * where). Called inside the transaction of the change it records, so a
 * rolled-back operation leaves no audit entry behind.
 */
class AuditLogger
{
    public function __construct(private readonly TenantContext $tenant) {}

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    public function record(string $event, Model $subject, ?array $old = null, ?array $new = null): AuditLog
    {
        $request = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();
        $companyId = $subject->getAttribute('company_id') ?? ($subject instanceof Company ? $subject->getKey() : $this->tenant->id());

        $entry = new AuditLog;
        $entry->forceFill([
            'company_id' => $companyId,
            'user_id' => Auth::id(),
            'event' => $event,
            'auditable_type' => $subject->getMorphClass(),
            'auditable_id' => (string) $subject->getKey(),
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? Str::limit((string) $request->userAgent(), 250, '') : null,
            'method' => $request?->method() ?? 'CLI',
            'url' => $request ? Str::limit($request->fullUrl(), 500, '') : 'console',
        ])->save();

        // Mirror to the dedicated long-retention log channel.
        Log::channel('audit')->info($event, [
            'company_id' => $companyId,
            'user_id' => $entry->user_id,
            'subject' => $entry->auditable_type.':'.$entry->auditable_id,
            'old' => $old,
            'new' => $new,
            'ip' => $entry->ip_address,
        ]);

        return $entry;
    }
}
