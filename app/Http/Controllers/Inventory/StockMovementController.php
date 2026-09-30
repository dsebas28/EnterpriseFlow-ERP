<?php

namespace App\Http\Controllers\Inventory;

use App\Actions\Inventory\AdjustStock;
use App\Actions\Inventory\RecordManualMovement;
use App\Actions\Inventory\TransferStock;
use App\Enums\StockMovementType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\AdjustStockRequest;
use App\Http\Requests\Inventory\ManualMovementRequest;
use App\Http\Requests\Inventory\TransferStockRequest;
use App\Http\Resources\StockMovementResource;
use App\Http\Resources\WarehouseResource;
use App\Models\Product;
use App\Models\Warehouse;
use App\Queries\StockMovementIndexQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class StockMovementController extends Controller
{
    public function index(Request $request, StockMovementIndexQuery $query): Response
    {
        Gate::authorize('inventory.view');

        $filters = $request->only(['product_id', 'warehouse_id', 'type', 'from', 'to', 'search']);
        $product = isset($filters['product_id']) ? Product::withTrashed()->find($filters['product_id']) : null;

        return Inertia::render('inventory/Movements', [
            'movements' => StockMovementResource::collection($query->paginate($filters)),
            'filters' => $filters,
            'product' => $product ? ['id' => $product->id, 'sku' => $product->sku, 'name' => $product->name] : null,
            'warehouses' => WarehouseResource::collection(Warehouse::withTrashed()->orderBy('name')->get()),
            'types' => array_map(
                fn (StockMovementType $type) => ['value' => $type->value, 'label' => $type->label()],
                StockMovementType::cases(),
            ),
        ]);
    }

    public function adjust(AdjustStockRequest $request, AdjustStock $adjust): RedirectResponse
    {
        $movement = $adjust->handle(
            $request->product(),
            $request->warehouse(),
            $request->integer('counted_quantity'),
            $request->validated('reason'),
        );

        return back()->with('status', sprintf('Stock adjusted by %+d. New balance: %d.', $movement->quantity, $movement->balance_after));
    }

    public function manual(ManualMovementRequest $request, RecordManualMovement $record): RedirectResponse
    {
        $movement = $record->handle(
            $request->product(),
            $request->warehouse(),
            $request->type(),
            $request->integer('quantity'),
            $request->validated('notes'),
            $request->unitCost(),
        );

        return back()->with('status', sprintf('%s recorded. New balance: %d.', $movement->type->label(), $movement->balance_after));
    }

    public function transfer(TransferStockRequest $request, TransferStock $transfer): RedirectResponse
    {
        $legs = $transfer->handle(
            $request->product(),
            $request->warehouse('from_warehouse_id'),
            $request->warehouse('to_warehouse_id'),
            $request->integer('quantity'),
            $request->validated('notes'),
        );

        return back()->with('status', sprintf('Transferred %d units.', $legs['in']->quantity));
    }
}
