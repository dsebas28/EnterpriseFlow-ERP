<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\StockItemResource;
use App\Queries\StockIndexQuery;
use App\Support\Api\ApiResponse;
use App\Support\Tenancy\TenantRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class InventoryController extends Controller
{
    /**
     * Stock on hand per product, optionally for a single warehouse.
     * `stock` filters by level: `in`, `low` (below minimum) or `out`.
     */
    public function index(Request $request, StockIndexQuery $query): JsonResponse
    {
        Gate::authorize('inventory.view');

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'warehouse_id' => ['nullable', 'string', TenantRule::exists('warehouses')],
            'category_id' => ['nullable', 'integer', TenantRule::exists('categories')],
            'stock' => ['nullable', Rule::in(['in', 'low', 'out'])],
            'sort' => ['nullable', Rule::in(['name', 'sku', 'on_hand', '-on_hand'])],
        ]);

        return ApiResponse::success(StockItemResource::collection($query->paginate($filters)));
    }
}
