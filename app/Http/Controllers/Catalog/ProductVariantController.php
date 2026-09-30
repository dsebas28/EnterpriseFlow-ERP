<?php

namespace App\Http\Controllers\Catalog;

use App\Actions\Catalog\DeleteProduct;
use App\Actions\Catalog\SaveVariant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\SaveVariantRequest;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Routes use scoped bindings: {variant} must be a child of {product}.
 */
class ProductVariantController extends Controller
{
    public function store(SaveVariantRequest $request, Product $product, SaveVariant $save): RedirectResponse
    {
        $variant = $save->handle($product, null, $request->toVariantData());

        return back()->with('status', "Variant {$variant->sku} added.");
    }

    public function update(SaveVariantRequest $request, Product $product, Product $variant, SaveVariant $save): RedirectResponse
    {
        $save->handle($product, $variant, $request->toVariantData());

        return back()->with('status', "Variant {$variant->sku} updated.");
    }

    public function destroy(Product $product, Product $variant, DeleteProduct $delete): RedirectResponse
    {
        Gate::authorize('update', $product);

        $delete->handle($variant);

        return back()->with('status', "Variant {$variant->sku} deleted.");
    }
}
