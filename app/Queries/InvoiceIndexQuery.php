<?php

namespace App\Queries;

use App\Models\Invoice;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * @phpstan-type InvoiceFilters array{search?: string|null, status?: string|null, customer_id?: string|null, from?: string|null, to?: string|null}
 */
final class InvoiceIndexQuery
{
    /**
     * @param  InvoiceFilters  $filters
     * @return Builder<Invoice>
     */
    public function builder(array $filters): Builder
    {
        return Invoice::query()
            ->with(['customer:id,name', 'sale:id,number'])
            ->when(trim((string) ($filters['search'] ?? '')), fn (Builder $q, string $search) => $q->where(fn (Builder $w) => $w
                ->whereLike('number', "%{$search}%")
                ->orWhereHas('customer', fn (Builder $c) => $c->whereLike('name', "%{$search}%"))))
            ->when($filters['status'] ?? null, fn (Builder $q, string $status) => $q->where('status', $status))
            ->when($filters['customer_id'] ?? null, fn (Builder $q, string $id) => $q->where('customer_id', $id))
            ->when($filters['from'] ?? null, fn (Builder $q, string $from) => $q->whereDate('due_date', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $q, string $to) => $q->whereDate('due_date', '<=', $to))
            ->orderByDesc('created_at');
    }

    /**
     * @param  InvoiceFilters  $filters
     * @return LengthAwarePaginator<int, Invoice>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        return $this->builder($filters)->paginate(20)->withQueryString();
    }
}
