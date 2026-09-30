<?php

namespace App\Http\Controllers\Purchasing;

use App\Actions\Purchasing\DeletePurchaseOrder;
use App\Actions\Purchasing\SavePurchaseOrder;
use App\Enums\PartyStatus;
use App\Enums\PurchaseOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Purchasing\SavePurchaseOrderRequest;
use App\Http\Resources\PurchaseOrderResource;
use App\Http\Resources\WarehouseResource;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\Warehouse;
use App\Queries\PurchaseOrderIndexQuery;
use App\Support\Money\Currency;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseOrderController extends Controller
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function index(Request $request, PurchaseOrderIndexQuery $query): Response
    {
        Gate::authorize('viewAny', PurchaseOrder::class);

        $filters = $request->only(['search', 'status', 'supplier_id', 'from', 'to']);

        return Inertia::render('purchasing/orders/Index', [
            'orders' => PurchaseOrderResource::collection($query->paginate($filters)),
            'filters' => $filters,
            'statuses' => array_map(
                fn (PurchaseOrderStatus $s) => ['value' => $s->value, 'label' => $s->label()],
                PurchaseOrderStatus::cases(),
            ),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', PurchaseOrder::class);

        return Inertia::render('purchasing/orders/Form', $this->formProps(null));
    }

    public function store(SavePurchaseOrderRequest $request, SavePurchaseOrder $save): RedirectResponse
    {
        $order = $save->handle(null, $request->toData(), $request->user());

        return to_route('purchasing.orders.show', $order)->with('status', "Purchase order {$order->number} created as draft.");
    }

    public function show(Request $request, PurchaseOrder $purchaseOrder): Response
    {
        Gate::authorize('view', $purchaseOrder);

        $purchaseOrder->load(['supplier', 'warehouse', 'items.product', 'receipts.items', 'receipts.warehouse', 'receipts.receiver', 'bills', 'creator', 'approver']);
        $user = $request->user();
        $today = now($this->tenant->companyOrFail()->timezone);

        return Inertia::render('purchasing/orders/Show', [
            'order' => PurchaseOrderResource::make($purchaseOrder),
            'warehouses' => WarehouseResource::collection(Warehouse::active()->orderBy('name')->get()),
            'billDefaults' => ['bill_date' => $today->toDateString(), 'due_date' => $today->copy()->addDays(30)->toDateString()],
            'can' => [
                'bill' => $purchaseOrder->items->contains(fn (PurchaseOrderItem $item) => $item->billableQuantity() > 0)
                    && ($user?->can('create', SupplierBill::class) ?? false),
                'edit' => $purchaseOrder->status->isEditable() && ($user?->can('update', $purchaseOrder) ?? false),
                'submit' => $purchaseOrder->status->canTransitionTo(PurchaseOrderStatus::Pending) && ($user?->can('update', $purchaseOrder) ?? false),
                'approve' => $purchaseOrder->status->canTransitionTo(PurchaseOrderStatus::Approved) && ($user?->can('approve', $purchaseOrder) ?? false),
                'returnToDraft' => $purchaseOrder->status === PurchaseOrderStatus::Pending && ($user?->can('approve', $purchaseOrder) ?? false),
                'receive' => $purchaseOrder->status->canReceive() && ($user?->can('receive', $purchaseOrder) ?? false),
                'cancel' => $purchaseOrder->status->canTransitionTo(PurchaseOrderStatus::Cancelled) && ($user?->can('cancel', $purchaseOrder) ?? false),
                'delete' => $purchaseOrder->status->isEditable() && ($user?->can('delete', $purchaseOrder) ?? false),
            ],
        ]);
    }

    public function edit(PurchaseOrder $purchaseOrder): Response|RedirectResponse
    {
        Gate::authorize('update', $purchaseOrder);

        if (! $purchaseOrder->status->isEditable()) {
            return to_route('purchasing.orders.show', $purchaseOrder)->withErrors(['rule' => 'Only draft orders can be edited.']);
        }

        $purchaseOrder->load(['supplier', 'warehouse', 'items.product']);

        return Inertia::render('purchasing/orders/Form', $this->formProps($purchaseOrder));
    }

    public function update(SavePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder, SavePurchaseOrder $save): RedirectResponse
    {
        $save->handle($purchaseOrder, $request->toData(), $request->user());

        return to_route('purchasing.orders.show', $purchaseOrder)->with('status', 'Purchase order updated.');
    }

    public function destroy(PurchaseOrder $purchaseOrder, DeletePurchaseOrder $delete): RedirectResponse
    {
        Gate::authorize('delete', $purchaseOrder);

        $delete->handle($purchaseOrder);

        return to_route('purchasing.orders.index')->with('status', "Draft {$purchaseOrder->number} deleted.");
    }

    /**
     * @return array<string, mixed>
     */
    private function formProps(?PurchaseOrder $order): array
    {
        $currency = $this->tenant->companyOrFail()->currency;

        return [
            'order' => $order ? PurchaseOrderResource::make($order) : null,
            'suppliers' => Supplier::where('status', PartyStatus::Active->value)->orderBy('name')->get(['id', 'name']),
            'warehouses' => WarehouseResource::collection(Warehouse::active()->orderByDesc('is_default')->orderBy('name')->get()),
            'currency' => ['code' => $currency, 'decimals' => Currency::minorUnits($currency)],
            'today' => now($this->tenant->companyOrFail()->timezone)->toDateString(),
        ];
    }
}
