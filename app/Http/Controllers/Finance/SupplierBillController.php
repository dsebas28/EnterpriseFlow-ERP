<?php

namespace App\Http\Controllers\Finance;

use App\Actions\Invoicing\CancelSupplierBill;
use App\Actions\Invoicing\RegisterSupplierBill;
use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\RegisterSupplierBillRequest;
use App\Http\Resources\SupplierBillResource;
use App\Models\PurchaseOrder;
use App\Models\SupplierBill;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SupplierBillController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', SupplierBill::class);

        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', '');

        $bills = SupplierBill::query()
            ->with(['supplier:id,name', 'purchaseOrder:id,number'])
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->whereLike('number', "%{$search}%")
                ->orWhereLike('supplier_reference', "%{$search}%")
                ->orWhereHas('supplier', fn (Builder $s) => $s->whereLike('name', "%{$search}%"))))
            ->when(InvoiceStatus::tryFrom($status), fn (Builder $q) => $q->where('status', $status))
            ->orderBy('due_date')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('finance/bills/Index', [
            'bills' => SupplierBillResource::collection($bills),
            'filters' => ['search' => $search, 'status' => $status],
            'statuses' => array_map(
                fn (InvoiceStatus $s) => ['value' => $s->value, 'label' => $s->label()],
                array_values(array_filter(InvoiceStatus::cases(), fn (InvoiceStatus $s) => $s !== InvoiceStatus::Draft)),
            ),
        ]);
    }

    public function store(RegisterSupplierBillRequest $request, PurchaseOrder $purchaseOrder, RegisterSupplierBill $register): RedirectResponse
    {
        $bill = $register->handle(
            $purchaseOrder,
            $request->validated('supplier_reference'),
            $request->validated('bill_date'),
            $request->validated('due_date'),
            $request->quantities(),
            $request->user(),
            $request->validated('notes'),
        );

        return to_route('finance.bills.show', $bill)->with('status', "Supplier bill {$bill->number} registered.");
    }

    public function show(Request $request, SupplierBill $bill): Response
    {
        Gate::authorize('view', $bill);

        $bill->load(['items', 'supplier', 'purchaseOrder', 'creator']);

        return Inertia::render('finance/bills/Show', [
            'bill' => SupplierBillResource::make($bill),
            'can' => [
                'cancel' => $bill->status->canTransitionTo(InvoiceStatus::Cancelled)
                    && $bill->amount_paid === 0
                    && ($request->user()?->can('cancel', $bill) ?? false),
            ],
        ]);
    }

    public function cancel(Request $request, SupplierBill $bill, CancelSupplierBill $cancel): RedirectResponse
    {
        Gate::authorize('cancel', $bill);

        $validated = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        $cancel->handle($bill, $request->user(), $validated['reason']);

        return back()->with('status', "Supplier bill {$bill->number} cancelled.");
    }
}
