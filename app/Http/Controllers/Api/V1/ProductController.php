<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Catalog\DeleteProduct;
use App\Actions\Catalog\SaveProduct;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\SaveProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Queries\ProductIndexQuery;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    /**
     * List products.
     *
     * Search matches name, SKU and barcode (including variants). Sort by
     * `name`, `sku`, `price` or `created`; prefix with `-` for descending.
     */
    public function index(Request $request, ProductIndexQuery $query): JsonResponse
    {
        Gate::authorize('viewAny', Product::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'type' => ['nullable', Rule::in(['simple', 'variable'])],
            'sort' => ['nullable', 'string', 'max:20'],
            'per_page' => ['nullable', 'integer', Rule::in(ProductIndexQuery::PER_PAGE)],
        ]);

        return ApiResponse::success(ProductResource::collection($query->paginate($filters)));
    }

    /**
     * Create a product. Money fields are decimal strings in the company currency.
     */
    public function store(SaveProductRequest $request, SaveProduct $save): JsonResponse
    {
        $product = $save->handle(null, $request->toData());

        return ApiResponse::created(ProductResource::make($product->load('category')), 'Product created successfully.');
    }

    public function show(Product $product): JsonResponse
    {
        Gate::authorize('view', $product);

        return ApiResponse::success(ProductResource::make($product->load(['category', 'variants', 'images'])));
    }

    public function update(SaveProductRequest $request, Product $product, SaveProduct $save): JsonResponse
    {
        $product = $save->handle($product, $request->toData($product->type));

        return ApiResponse::success(ProductResource::make($product->load('category')), 'Product updated successfully.');
    }

    public function destroy(Product $product, DeleteProduct $delete): JsonResponse
    {
        Gate::authorize('delete', $product);

        $delete->handle($product);

        return ApiResponse::success(null, 'Product deleted successfully.');
    }
}
