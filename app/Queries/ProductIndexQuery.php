<?php

namespace App\Queries;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Catalogue listing shared by the web UI and the API: search, filters and
 * sorting are applied from an allow-list, never from raw client columns.
 */
final class ProductIndexQuery
{
    /**
     * Sort keys exposed to clients => database column.
     */
    public const SORTS = [
        'name' => 'name',
        'sku' => 'sku',
        'price' => 'price',
        'created' => 'created_at',
    ];

    public const PER_PAGE = [10, 20, 50, 100];

    /**
     * @param  array{search?: string|null, category_id?: int|string|null, status?: string|null, type?: string|null, sort?: string|null}  $filters
     * @return Builder<Product>
     */
    public function builder(array $filters): Builder
    {
        $query = Product::query()
            ->catalogue()
            ->with(['category:id,name', 'images'])
            ->withCount('variants');

        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $query->where(fn (Builder $q) => $q
                ->whereLike('name', "%{$search}%")
                ->orWhereLike('sku', "%{$search}%")
                ->orWhereLike('barcode', "%{$search}%")
                // Finding a variant's SKU/barcode surfaces its parent product.
                ->orWhereHas('variants', fn (Builder $v) => $v
                    ->whereLike('sku', "%{$search}%")
                    ->orWhereLike('barcode', "%{$search}%")));
        }

        if (! empty($filters['category_id'])) {
            $category = Category::find((int) $filters['category_id']);
            // Unknown or foreign category: return nothing rather than everything.
            $query->whereIn('category_id', $category?->selfAndDescendantIds() ?? [0]);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        [$column, $direction] = self::parseSort($filters['sort'] ?? null);

        return $query->orderBy($column, $direction)->orderBy('id');
    }

    /**
     * @param  array{search?: string|null, category_id?: int|string|null, status?: string|null, type?: string|null, sort?: string|null, per_page?: int|string|null}  $filters
     * @return LengthAwarePaginator<int, Product>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 20);

        return $this->builder($filters)
            ->paginate(in_array($perPage, self::PER_PAGE, true) ? $perPage : 20)
            ->withQueryString();
    }

    /**
     * "price" => ascending, "-price" => descending; unknown keys fall back
     * to newest first.
     *
     * @return array{0: string, 1: 'asc'|'desc'}
     */
    public static function parseSort(?string $sort): array
    {
        $sort = (string) $sort;
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $key = ltrim($sort, '-');

        return isset(self::SORTS[$key]) ? [self::SORTS[$key], $direction] : ['created_at', 'desc'];
    }
}
