<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Purchasing\SavePurchaseOrder;
use App\Enums\PurchaseOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Purchasing\SavePurchaseOrderRequest;
use App\Http\Resources\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use App\Queries\PurchaseOrderIndexQuery;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class PurchaseOrderController extends Controller
{
    /**
     * List purchase orders, newest first.
     */
    public function index(Request $request, PurchaseOrderIndexQuery $query): JsonResponse
    {
        Gate::authorize('viewAny', PurchaseOrder::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(PurchaseOrderStatus::class)],
            'supplier_id' => ['nullable', 'string'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        return ApiResponse::success(PurchaseOrderResource::collection($query->paginate($filters)));
    }

    /**
     * Create a draft purchase order. Totals are computed by the server.
     */
    public function store(SavePurchaseOrderRequest $request, SavePurchaseOrder $save): JsonResponse
    {
        $order = $save->handle(null, $request->toData(), $request->user());

        return ApiResponse::created(
            PurchaseOrderResource::make($order->load(['supplier', 'warehouse', 'items.product'])),
            'Purchase order created successfully.',
        );
    }

    public function show(PurchaseOrder $purchaseOrder): JsonResponse
    {
        Gate::authorize('view', $purchaseOrder);

        return ApiResponse::success(PurchaseOrderResource::make($purchaseOrder->load(['supplier', 'warehouse', 'items.product'])));
    }
}
