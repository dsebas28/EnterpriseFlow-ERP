<?php

namespace App\Queries;

use App\Models\Sale;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * @phpstan-type SaleFilters array{search?: string|null, status?: string|null, customer_id?: string|null, from?: string|null, to?: string|null}
 */
final class SaleIndexQuery
{
    /**
     * @param  SaleFilters  $filters
     * @return Builder<Sale>
     */
    public function builder(array $filters): Builder
    {
        return Sale::query()
            ->with(['customer:id,name', 'warehouse:id,name'])
            ->when(trim((string) ($filters['search'] ?? '')), fn (Builder $q, string $search) => $q->where(fn (Builder $w) => $w
                ->whereLike('number', "%{$search}%")
                ->orWhereHas('customer', fn (Builder $c) => $c->whereLike('name', "%{$search}%"))))
            ->when($filters['status'] ?? null, fn (Builder $q, string $status) => $q->where('status', $status))
            ->when($filters['customer_id'] ?? null, fn (Builder $q, string $id) => $q->where('customer_id', $id))
            ->when($filters['from'] ?? null, fn (Builder $q, string $from) => $q->whereDate('sale_date', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $q, string $to) => $q->whereDate('sale_date', '<=', $to))
            ->orderByDesc('sale_date')
            ->orderByDesc('number');
    }

    /**
     * @param  SaleFilters  $filters
     * @return LengthAwarePaginator<int, Sale>
     */
    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->builder($filters)->paginate($perPage)->withQueryString();
    }
}
