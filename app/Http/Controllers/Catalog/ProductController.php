<?php

namespace App\Http\Controllers\Catalog;

use App\Actions\Catalog\DeleteProduct;
use App\Actions\Catalog\SaveProduct;
use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\SaveProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Queries\CategoryTreeQuery;
use App\Queries\ProductIndexQuery;
use App\Support\Money\Currency;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function __construct(
        private readonly CategoryTreeQuery $categories,
        private readonly TenantContext $tenant,
    ) {}

    public function index(Request $request, ProductIndexQuery $query): Response
    {
        Gate::authorize('viewAny', Product::class);

        $filters = $request->only(['search', 'category_id', 'status', 'type', 'sort', 'per_page']);

        return Inertia::render('catalog/products/Index', [
            'products' => ProductResource::collection($query->paginate($filters)),
            'filters' => $filters,
            'categories' => $this->categories->get(),
            'sorts' => array_keys(ProductIndexQuery::SORTS),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Product::class);

        return Inertia::render('catalog/products/Form', $this->formProps(null));
    }

    public function store(SaveProductRequest $request, SaveProduct $save): RedirectResponse
    {
        $product = $save->handle(null, $request->toData());

        return to_route('catalog.products.edit', $product)->with('status', 'Product created.');
    }

    public function edit(Product $product): Response
    {
        Gate::authorize('view', $product);

        // Variants are managed inside their parent's page.
        if ($product->parent_id !== null) {
            return $this->edit($product->parent()->firstOrFail());
        }

        $product->load(['category:id,name', 'images', 'variants']);

        return Inertia::render('catalog/products/Form', $this->formProps($product));
    }

    public function update(SaveProductRequest $request, Product $product, SaveProduct $save): RedirectResponse
    {
        $save->handle($product, $request->toData($product->type));

        return back()->with('status', 'Product updated.');
    }

    public function destroy(Product $product, DeleteProduct $delete): RedirectResponse
    {
        Gate::authorize('delete', $product);

        $delete->handle($product);

        return to_route('catalog.products.index')->with('status', "Product {$product->sku} deleted.");
    }

    /**
     * @return array<string, mixed>
     */
    private function formProps(?Product $product): array
    {
        $currency = $this->tenant->companyOrFail()->currency;

        return [
            'product' => $product ? ProductResource::make($product) : null,
            'categories' => $this->categories->get(),
            'currency' => ['code' => $currency, 'decimals' => Currency::minorUnits($currency)],
            'statuses' => array_map(fn (ProductStatus $s) => ['value' => $s->value, 'label' => $s->label()], ProductStatus::cases()),
            'types' => array_map(
                fn (ProductType $t) => ['value' => $t->value, 'label' => $t->label()],
                [ProductType::Simple, ProductType::Variable],
            ),
            'maxImages' => Product::MAX_IMAGES,
        ];
    }
}
