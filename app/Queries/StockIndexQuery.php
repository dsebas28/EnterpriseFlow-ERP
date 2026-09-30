<?php

namespace App\Queries;

use App\Models\Product;
use App\Models\StockLevel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Stock on hand per stockable product (simple products and variants),
 * optionally restricted to one warehouse, with low/out-of-stock filters.
 *
 * @phpstan-type StockFilters array{search?: string|null, warehouse_id?: string|null, category_id?: int|string|null, stock?: string|null, sort?: string|null}
 */
final class StockIndexQuery
{
    public const SORTS = [
        'name' => 'products.name',
        'sku' => 'products.sku',
        'on_hand' => 'on_hand',
    ];

    /**
     * @param  StockFilters  $filters
     * @return Builder<Product>
     */
    public function builder(array $filters): Builder
    {
        $warehouseId = $filters['warehouse_id'] ?? null;

        // Tenant-scoped aggregate (StockLevel carries the company scope).
        $totals = StockLevel::query()
            ->selectRaw('product_id, SUM(quantity) as on_hand')
            ->when($warehouseId, fn ($query) => $query->where('warehouse_id', $warehouseId))
            ->groupBy('product_id');

        $onHand = 'COALESCE(totals.on_hand, 0)';

        $query = Product::query()
            ->stockable()
            ->leftJoinSub($totals, 'totals', 'totals.product_id', '=', 'products.id')
            ->select('products.*')
            ->selectRaw("{$onHand} as on_hand")
            ->with(['category:id,name', 'stockLevels']);

        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $query->where(fn (Builder $q) => $q
                ->whereLike('products.name', "%{$search}%")
                ->orWhereLike('products.sku', "%{$search}%")
                ->orWhereLike('products.barcode', "%{$search}%"));
        }

        if (! empty($filters['category_id'])) {
            $query->where('products.category_id', (int) $filters['category_id']);
        }

        // Fixed SQL fragments only; no user input is interpolated.
        match ($filters['stock'] ?? null) {
            'out' => $query->whereRaw("{$onHand} <= 0"),
            'low' => $query->whereRaw("{$onHand} > 0")->whereRaw("{$onHand} < products.min_stock"),
            'in' => $query->whereRaw("{$onHand} > 0"),
            default => null,
        };

        $sort = (string) ($filters['sort'] ?? 'name');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = self::SORTS[ltrim($sort, '-')] ?? 'products.name';

        return $query->orderBy($column, $direction)->orderBy('products.id');
    }

    /**
     * @param  StockFilters  $filters
     * @return LengthAwarePaginator<int, Product>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        return $this->builder($filters)->paginate(25)->withQueryString();
    }

    /**
     * Headline figures for the current filter's warehouse scope.
     *
     * @return array{items: int, units: int, value: int, low: int, out: int}
     */
    public function summary(?string $warehouseId): array
    {
        $rows = $this->builder(['warehouse_id' => $warehouseId])
            ->reorder()
            ->get(['products.id', 'products.cost', 'products.min_stock']);

        $onHand = fn (Product $p): int => (int) $p->getAttribute('on_hand');

        return [
            'items' => $rows->count(),
            'units' => $rows->sum($onHand),
            // Valued at the current product cost (standard cost).
            'value' => $rows->sum(fn (Product $p) => max(0, $onHand($p)) * $p->cost),
            'low' => $rows->filter(fn (Product $p) => $onHand($p) > 0 && $onHand($p) < $p->min_stock)->count(),
            'out' => $rows->filter(fn (Product $p) => $onHand($p) <= 0)->count(),
        ];
    }
}
