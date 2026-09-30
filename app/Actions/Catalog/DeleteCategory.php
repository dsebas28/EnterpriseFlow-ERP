<?php

namespace App\Actions\Catalog;

use App\Exceptions\BusinessRuleViolation;
use App\Models\Category;

final class DeleteCategory
{
    public function handle(Category $category): void
    {
        if ($category->children()->exists()) {
            throw new BusinessRuleViolation('Move or delete the subcategories first.');
        }

        if ($category->products()->exists()) {
            throw new BusinessRuleViolation('This category still has products. Reassign them before deleting it.');
        }

        $category->delete();
    }
}
