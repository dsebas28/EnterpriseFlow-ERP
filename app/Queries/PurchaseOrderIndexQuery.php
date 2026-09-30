<?php

namespace App\Queries;

use App\Models\PurchaseOrder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * @phpstan-type PurchaseFilters array{search?: string|null, status?: string|null, supplier_id?: string|null, from?: string|null, to?: string|null}
 */
final class PurchaseOrderIndexQuery
{
    /**
     * @param  PurchaseFilters  $filters
     * @return Builder<PurchaseOrder>
     */
    public function builder(array $filters): Builder
    {
        return PurchaseOrder::query()
            ->with(['supplier:id,name', 'warehouse:id,name'])
            ->when(trim((string) ($filters['search'] ?? '')), fn (Builder $q, string $search) => $q->where(fn (Builder $w) => $w
                ->whereLike('number', "%{$search}%")
                ->orWhereHas('supplier', fn (Builder $s) => $s->whereLike('name', "%{$search}%"))))
            ->when($filters['status'] ?? null, fn (Builder $q, string $status) => $q->where('status', $status))
            ->when($filters['supplier_id'] ?? null, fn (Builder $q, string $id) => $q->where('supplier_id', $id))
            ->when($filters['from'] ?? null, fn (Builder $q, string $from) => $q->whereDate('order_date', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $q, string $to) => $q->whereDate('order_date', '<=', $to))
            ->orderByDesc('order_date')
            ->orderByDesc('number');
    }

    /**
     * @param  PurchaseFilters  $filters
     * @return LengthAwarePaginator<int, PurchaseOrder>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        return $this->builder($filters)->paginate(20)->withQueryString();
    }
}
