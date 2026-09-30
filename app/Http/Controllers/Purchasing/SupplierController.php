<?php

namespace App\Http\Controllers\Purchasing;

use App\Actions\Purchasing\DeleteSupplier;
use App\Enums\PurchaseOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Purchasing\SaveSupplierRequest;
use App\Http\Resources\SupplierResource;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Supplier::class);

        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', '');

        $suppliers = Supplier::query()
            ->withCount(['purchaseOrders' => fn (Builder $q) => $q->whereNotIn('status', [
                PurchaseOrderStatus::Received->value,
                PurchaseOrderStatus::Cancelled->value,
            ])])
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->whereLike('name', "%{$search}%")
                ->orWhereLike('tax_id', "%{$search}%")
                ->orWhereLike('email', "%{$search}%")))
            ->when(in_array($status, ['active', 'inactive'], true), fn (Builder $q) => $q->where('status', $status))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('purchasing/Suppliers', [
            'suppliers' => SupplierResource::collection($suppliers),
            'filters' => ['search' => $search, 'status' => $status],
        ]);
    }

    public function store(SaveSupplierRequest $request): RedirectResponse
    {
        $supplier = Supplier::create($request->validated());

        return back()->with('status', "Supplier {$supplier->name} created.");
    }

    public function update(SaveSupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($request->validated());

        return back()->with('status', "Supplier {$supplier->name} updated.");
    }

    public function destroy(Supplier $supplier, DeleteSupplier $delete): RedirectResponse
    {
        Gate::authorize('delete', $supplier);

        $delete->handle($supplier);

        return back()->with('status', "Supplier {$supplier->name} deleted.");
    }
}
