<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Sales\CancelSale;
use App\Actions\Sales\ConfirmSale;
use App\Actions\Sales\SaveSale;
use App\Enums\SaleStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\SaveSaleRequest;
use App\Http\Resources\SaleResource;
use App\Models\Sale;
use App\Queries\SaleIndexQuery;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SaleController extends Controller
{
    /**
     * List sales, newest first.
     */
    public function index(Request $request, SaleIndexQuery $query): JsonResponse
    {
        Gate::authorize('viewAny', Sale::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(SaleStatus::class)],
            'customer_id' => ['nullable', 'string'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return ApiResponse::success(SaleResource::collection($query->paginate($filters, (int) ($filters['per_page'] ?? 20))));
    }

    /**
     * Create a sale as a draft. Totals are computed by the server; send
     * `"confirm": true` to also confirm it (deducting stock) in one call.
     */
    public function store(SaveSaleRequest $request, SaveSale $save, ConfirmSale $confirm): JsonResponse
    {
        $sale = $save->handle(null, $request->toData(), $request->user());

        if ($request->boolean('confirm')) {
            Gate::authorize('confirm', $sale);
            $sale = $confirm->handle($sale, $request->user());
        }

        return ApiResponse::created(SaleResource::make($sale->load(['customer', 'warehouse', 'items.product'])), 'Sale created successfully.');
    }

    public function show(Sale $sale): JsonResponse
    {
        Gate::authorize('view', $sale);

        return ApiResponse::success(SaleResource::make($sale->load(['customer', 'warehouse', 'items.product'])));
    }

    /**
     * Confirm a sale: stock is deducted atomically for every line.
     */
    public function confirm(Request $request, Sale $sale, ConfirmSale $confirm): JsonResponse
    {
        Gate::authorize('confirm', $sale);

        $sale = $confirm->handle($sale, $request->user());

        return ApiResponse::success(SaleResource::make($sale->load(['customer', 'warehouse', 'items.product'])), 'Sale confirmed.');
    }

    /**
     * Cancel a sale. Deducted stock is returned through compensating movements.
     */
    public function cancel(Request $request, Sale $sale, CancelSale $cancel): JsonResponse
    {
        Gate::authorize('cancel', $sale);

        $validated = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $sale = $cancel->handle($sale, $request->user(), $validated['reason']);

        return ApiResponse::success(SaleResource::make($sale->load(['customer', 'warehouse', 'items.product'])), 'Sale cancelled.');
    }
}
