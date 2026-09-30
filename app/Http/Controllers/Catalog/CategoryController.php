<?php

namespace App\Http\Controllers\Catalog;

use App\Actions\Catalog\DeleteCategory;
use App\Actions\Catalog\SaveCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\SaveCategoryRequest;
use App\Models\Category;
use App\Queries\CategoryTreeQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(CategoryTreeQuery $tree): Response
    {
        Gate::authorize('viewAny', Category::class);

        return Inertia::render('catalog/Categories', [
            'categories' => $tree->get(),
        ]);
    }

    public function store(SaveCategoryRequest $request, SaveCategory $save): RedirectResponse
    {
        $category = $save->handle(null, $request->validated('name'), $this->parentId($request), $request->validated('description'));

        return back()->with('status', "Category {$category->name} created.");
    }

    public function update(SaveCategoryRequest $request, Category $category, SaveCategory $save): RedirectResponse
    {
        $save->handle($category, $request->validated('name'), $this->parentId($request), $request->validated('description'));

        return back()->with('status', "Category {$category->name} updated.");
    }

    public function destroy(Category $category, DeleteCategory $delete): RedirectResponse
    {
        Gate::authorize('delete', $category);

        $delete->handle($category);

        return back()->with('status', "Category {$category->name} deleted.");
    }

    private function parentId(SaveCategoryRequest $request): ?int
    {
        $parentId = $request->validated('parent_id');

        return $parentId === null ? null : (int) $parentId;
    }
}
