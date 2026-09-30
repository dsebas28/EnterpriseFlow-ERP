<?php

namespace App\Http\Controllers\Catalog;

use App\Actions\Warehouses\DeleteWarehouse;
use App\Actions\Warehouses\SaveWarehouse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\SaveWarehouseRequest;
use App\Http\Resources\WarehouseResource;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class WarehouseController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Warehouse::class);

        return Inertia::render('inventory/Warehouses', [
            'warehouses' => WarehouseResource::collection(
                Warehouse::orderByDesc('is_default')->orderBy('name')->get(),
            ),
        ]);
    }

    public function store(SaveWarehouseRequest $request, SaveWarehouse $save): RedirectResponse
    {
        $warehouse = $save->handle(null, $request->validated());

        return back()->with('status', "Warehouse {$warehouse->name} created.");
    }

    public function update(SaveWarehouseRequest $request, Warehouse $warehouse, SaveWarehouse $save): RedirectResponse
    {
        $save->handle($warehouse, $request->validated());

        return back()->with('status', "Warehouse {$warehouse->name} updated.");
    }

    public function destroy(Warehouse $warehouse, DeleteWarehouse $delete): RedirectResponse
    {
        Gate::authorize('delete', $warehouse);

        $delete->handle($warehouse);

        return back()->with('status', "Warehouse {$warehouse->name} deleted.");
    }
}
