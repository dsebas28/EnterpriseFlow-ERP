<?php

namespace App\Queries;

use App\Models\StockMovement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * The inventory ledger (kardex), newest first.
 *
 * @phpstan-type MovementFilters array{product_id?: string|null, warehouse_id?: string|null, type?: string|null, from?: string|null, to?: string|null, search?: string|null}
 */
final class StockMovementIndexQuery
{
    /**
     * @param  MovementFilters  $filters
     * @return Builder<StockMovement>
     */
    public function builder(array $filters): Builder
    {
        return StockMovement::query()
            ->with(['product:id,sku,name', 'warehouse:id,code,name', 'user:id,name'])
            ->when($filters['product_id'] ?? null, fn (Builder $q, string $id) => $q->where('product_id', $id))
            ->when($filters['warehouse_id'] ?? null, fn (Builder $q, string $id) => $q->where('warehouse_id', $id))
            ->when($filters['type'] ?? null, fn (Builder $q, string $type) => $q->where('type', $type))
            ->when($filters['from'] ?? null, fn (Builder $q, string $from) => $q->where('occurred_at', '>=', $from.' 00:00:00'))
            ->when($filters['to'] ?? null, fn (Builder $q, string $to) => $q->where('occurred_at', '<=', $to.' 23:59:59'))
            ->when(trim((string) ($filters['search'] ?? '')), fn (Builder $q, string $search) => $q->whereHas(
                'product',
                fn (Builder $p) => $p->whereLike('name', "%{$search}%")->orWhereLike('sku', "%{$search}%"),
            ))
            ->orderByDesc('id');
    }

    /**
     * @param  MovementFilters  $filters
     * @return LengthAwarePaginator<int, StockMovement>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        return $this->builder($filters)->paginate(30)->withQueryString();
    }
}
