<?php

namespace App\Queries;

use App\Models\Category;

/**
 * The company's categories as a flattened, depth-annotated tree, ready for
 * indented selects and lists. One query, assembled in memory.
 *
 * @phpstan-type CategoryNode array{id: int, parent_id: int|null, name: string, slug: string, description: string|null, depth: int, products_count: int}
 */
final class CategoryTreeQuery
{
    /**
     * @return list<CategoryNode>
     */
    public function get(): array
    {
        /** @var array<int, list<Category>> $childrenOf keyed by parent id (0 = root) */
        $childrenOf = [];

        foreach (Category::query()->withCount('products')->orderBy('name')->get() as $category) {
            $childrenOf[$category->parent_id ?? 0][] = $category;
        }

        $flat = [];
        $this->walk($childrenOf, 0, 0, $flat);

        return $flat;
    }

    /**
     * @param  array<int, list<Category>>  $childrenOf
     * @param  list<CategoryNode>  $flat
     */
    private function walk(array $childrenOf, int $parentId, int $depth, array &$flat): void
    {
        foreach ($childrenOf[$parentId] ?? [] as $category) {
            $flat[] = [
                'id' => $category->id,
                'parent_id' => $category->parent_id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'depth' => $depth,
                'products_count' => (int) $category->getAttribute('products_count'),
            ];

            $this->walk($childrenOf, $category->id, $depth + 1, $flat);
        }
    }
}
