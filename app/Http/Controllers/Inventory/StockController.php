<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Resources\MoneyResource;
use App\Http\Resources\StockItemResource;
use App\Http\Resources\WarehouseResource;
use App\Models\Warehouse;
use App\Queries\CategoryTreeQuery;
use App\Queries\StockIndexQuery;
use App\Support\Money\Money;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class StockController extends Controller
{
    public function index(Request $request, StockIndexQuery $query, CategoryTreeQuery $categories, TenantContext $tenant): Response
    {
        Gate::authorize('inventory.view');

        $filters = $request->only(['search', 'warehouse_id', 'category_id', 'stock', 'sort']);
        $summary = $query->summary($filters['warehouse_id'] ?? null);

        return Inertia::render('inventory/Stock', [
            'items' => StockItemResource::collection($query->paginate($filters)),
            'summary' => [
                ...$summary,
                'value' => MoneyResource::make(new Money($summary['value'], $tenant->companyOrFail()->currency)),
            ],
            'warehouses' => WarehouseResource::collection(Warehouse::orderByDesc('is_default')->orderBy('name')->get()),
            'categories' => $categories->get(),
            'filters' => $filters,
            'can' => [
                'adjust' => $request->user()?->can('inventory.adjust') ?? false,
                'transfer' => $request->user()?->can('inventory.transfer') ?? false,
            ],
        ]);
    }
}
