<?php

namespace App\Http\Controllers\Catalog;

use App\Actions\Catalog\AddProductImage;
use App\Actions\Catalog\DeleteProductImage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreProductImageRequest;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class ProductImageController extends Controller
{
    public function store(StoreProductImageRequest $request, Product $product, AddProductImage $add): RedirectResponse
    {
        $add->handle($product, $request->file('image'));

        return back()->with('status', 'Image uploaded.');
    }

    public function destroy(Product $product, ProductImage $image, DeleteProductImage $delete): RedirectResponse
    {
        Gate::authorize('update', $product);

        $delete->handle($image);

        return back()->with('status', 'Image removed.');
    }
}
