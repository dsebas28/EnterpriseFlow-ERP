<?php

namespace App\Actions\Catalog;

use App\Exceptions\BusinessRuleViolation;
use App\Models\Category;
use Illuminate\Support\Str;

final class SaveCategory
{
    public function handle(?Category $category, string $name, ?int $parentId, ?string $description): Category
    {
        $category ??= new Category;

        if ($category->exists && $parentId !== null && in_array($parentId, $category->selfAndDescendantIds(), true)) {
            throw new BusinessRuleViolation('A category cannot be moved inside itself or one of its subcategories.');
        }

        if (! $category->exists || $category->name !== $name) {
            $category->slug = $this->uniqueSlug($name, $category->id);
        }

        $category->fill([
            'name' => $name,
            'parent_id' => $parentId,
            'description' => $description,
        ])->save();

        return $category;
    }

    private function uniqueSlug(string $name, ?int $ignoreId): string
    {
        $base = Str::slug($name) ?: 'category';
        $slug = $base;

        // withTrashed: slugs of soft-deleted categories stay reserved (unique index).
        for ($i = 2; Category::withTrashed()->where('slug', $slug)->whereKeyNot($ignoreId ?? 0)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }
}
