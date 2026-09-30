<?php

namespace App\Http\Controllers\Sales;

use App\Actions\Sales\DeleteSale;
use App\Actions\Sales\SaveSale;
use App\Enums\PartyStatus;
use App\Enums\SaleStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\SaveSaleRequest;
use App\Http\Resources\SaleResource;
use App\Http\Resources\WarehouseResource;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\Warehouse;
use App\Queries\SaleIndexQuery;
use App\Support\Money\Currency;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SaleController extends Controller
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function index(Request $request, SaleIndexQuery $query): Response
    {
        Gate::authorize('viewAny', Sale::class);

        $filters = $request->only(['search', 'status', 'customer_id', 'from', 'to']);

        return Inertia::render('sales/orders/Index', [
            'sales' => SaleResource::collection($query->paginate($filters)),
            'filters' => $filters,
            'statuses' => array_map(fn (SaleStatus $s) => ['value' => $s->value, 'label' => $s->label()], SaleStatus::cases()),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Sale::class);

        return Inertia::render('sales/orders/Form', [
            ...$this->formProps(null),
            'preselectedCustomerId' => $request->query('customer_id'),
        ]);
    }

    public function store(SaveSaleRequest $request, SaveSale $save): RedirectResponse
    {
        $sale = $save->handle(null, $request->toData(), $request->user());

        return to_route('sales.orders.show', $sale)->with('status', "Sale {$sale->number} saved as draft.");
    }

    public function show(Request $request, Sale $sale): Response
    {
        Gate::authorize('view', $sale);

        $sale->load(['customer', 'warehouse', 'items.product', 'creator', 'confirmer']);
        $user = $request->user();

        return Inertia::render('sales/orders/Show', [
            'sale' => SaleResource::make($sale),
            'can' => [
                'edit' => $sale->status->isEditable() && ($user?->can('update', $sale) ?? false),
                'markPending' => $sale->status->canTransitionTo(SaleStatus::Pending) && ($user?->can('update', $sale) ?? false),
                'returnToDraft' => $sale->status === SaleStatus::Pending && ($user?->can('update', $sale) ?? false),
                'confirm' => $sale->status->canTransitionTo(SaleStatus::Confirmed) && ($user?->can('confirm', $sale) ?? false),
                'cancel' => $sale->status->canTransitionTo(SaleStatus::Cancelled) && $sale->amount_paid === 0 && ($user?->can('cancel', $sale) ?? false),
                'delete' => $sale->status->isEditable() && ($user?->can('delete', $sale) ?? false),
            ],
        ]);
    }

    public function edit(Sale $sale): Response|RedirectResponse
    {
        Gate::authorize('update', $sale);

        if (! $sale->status->isEditable()) {
            return to_route('sales.orders.show', $sale)->withErrors(['rule' => 'Only draft sales can be edited.']);
        }

        $sale->load(['customer', 'warehouse', 'items.product']);

        return Inertia::render('sales/orders/Form', [...$this->formProps($sale), 'preselectedCustomerId' => null]);
    }

    public function update(SaveSaleRequest $request, Sale $sale, SaveSale $save): RedirectResponse
    {
        $save->handle($sale, $request->toData(), $request->user());

        return to_route('sales.orders.show', $sale)->with('status', 'Sale updated.');
    }

    public function destroy(Sale $sale, DeleteSale $delete): RedirectResponse
    {
        Gate::authorize('delete', $sale);

        $delete->handle($sale);

        return to_route('sales.orders.index')->with('status', "Draft {$sale->number} deleted.");
    }

    /**
     * @return array<string, mixed>
     */
    private function formProps(?Sale $sale): array
    {
        $company = $this->tenant->companyOrFail();

        return [
            'sale' => $sale ? SaleResource::make($sale) : null,
            'customers' => Customer::where('status', PartyStatus::Active->value)->orderBy('name')->get(['id', 'name']),
            'warehouses' => WarehouseResource::collection(Warehouse::active()->orderByDesc('is_default')->orderBy('name')->get()),
            'currency' => ['code' => $company->currency, 'decimals' => Currency::minorUnits($company->currency)],
            'today' => now($company->timezone)->toDateString(),
        ];
    }
}
