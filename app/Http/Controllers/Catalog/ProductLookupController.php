<?php

namespace App\Http\Controllers\Catalog;

use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Support\Money\BasisPoints;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Autocomplete for document lines: active, stockable products matching a
 * term. Session-authenticated JSON used by the purchase and sales forms.
 */
class ProductLookupController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()?->canAny(['products.view', 'purchases.create', 'sales.create']) ?? false,
            403,
        );

        $term = trim((string) $request->query('q', ''));

        $products = Product::query()
            ->stockable()
            ->where('status', ProductStatus::Active->value)
            ->when($term !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->whereLike('name', "%{$term}%")
                ->orWhereLike('sku', "%{$term}%")
                ->orWhere('barcode', $term)))
            ->orderBy('name')
            ->limit(15)
            ->get();

        return response()->json($products->map(fn (Product $product) => [
            'id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'cost' => $product->costMoney()->toDecimal(),
            'price' => $product->priceMoney()->toDecimal(),
            'tax_rate' => BasisPoints::toPercent($product->tax_rate),
        ]));
    }
}
