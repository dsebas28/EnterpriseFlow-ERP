<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request, TenantContext $tenant): Response
    {
        Gate::authorize('audit.view');

        $types = array_keys(Relation::morphMap());
        $filters = $request->validate([
            'type' => ['nullable', Rule::in($types)],
            'id' => ['nullable', 'string', 'max:26'],
            'user_id' => ['nullable', 'integer'],
            'event' => ['nullable', 'string', 'max:60'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $logs = AuditLog::query()
            ->with('user:id,name')
            ->when($filters['type'] ?? null, fn (Builder $q, string $type) => $q->where('auditable_type', $type))
            ->when($filters['id'] ?? null, fn (Builder $q, string $id) => $q->where('auditable_id', $id))
            ->when($filters['user_id'] ?? null, fn (Builder $q, int $id) => $q->where('user_id', $id))
            ->when($filters['event'] ?? null, fn (Builder $q, string $event) => $q->where('event', $event))
            ->when($filters['from'] ?? null, fn (Builder $q, string $from) => $q->whereDate('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $q, string $to) => $q->whereDate('created_at', '<=', $to))
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (AuditLog $log) => [
                'id' => $log->id,
                'event' => $log->event,
                'type' => $log->auditable_type,
                'subject_id' => $log->auditable_id,
                'user' => $log->user?->name,
                'changes' => $this->changes($log),
                'ip_address' => $log->ip_address,
                'user_agent' => $log->user_agent,
                'method' => $log->method,
                'url' => $log->url,
                'created_at' => $log->created_at->toIso8601String(),
            ]);

        return Inertia::render('audit/Index', [
            'logs' => $logs,
            'filters' => $filters,
            'types' => $types,
            'events' => AuditLog::query()->distinct()->orderBy('event')->pluck('event'),
            'users' => $tenant->companyOrFail()->users()->orderBy('name')->get(['users.id', 'users.name'])
                ->map(fn ($user) => ['id' => $user->id, 'name' => $user->name]),
        ]);
    }

    /**
     * Field-level diff: one row per attribute with its previous and new value.
     *
     * @return list<array{field: string, old: mixed, new: mixed}>
     */
    private function changes(AuditLog $log): array
    {
        $old = $log->old_values ?? [];
        $new = $log->new_values ?? [];
        $rows = [];

        foreach (array_unique([...array_keys($old), ...array_keys($new)]) as $field) {
            $rows[] = ['field' => (string) $field, 'old' => $old[$field] ?? null, 'new' => $new[$field] ?? null];
        }

        return $rows;
    }
}
