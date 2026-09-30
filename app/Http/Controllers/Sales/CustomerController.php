<?php

namespace App\Http\Controllers\Sales;

use App\Actions\Sales\DeleteCustomer;
use App\Enums\SaleStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\SaveCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\MoneyResource;
use App\Http\Resources\SaleResource;
use App\Models\Customer;
use App\Models\CustomerNote;
use App\Models\Sale;
use App\Queries\SaleIndexQuery;
use App\Support\Money\Money;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Customer::class);

        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', '');

        $customers = Customer::query()
            // Outstanding balance per customer in the same query.
            ->withSum(['sales as receivable_total' => fn (Builder $q) => $q->whereIn('status', SaleStatus::receivableValues())], 'total')
            ->withSum(['sales as receivable_paid' => fn (Builder $q) => $q->whereIn('status', SaleStatus::receivableValues())], 'amount_paid')
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->whereLike('name', "%{$search}%")
                ->orWhereLike('tax_id', "%{$search}%")
                ->orWhereLike('email', "%{$search}%")))
            ->when(in_array($status, ['active', 'inactive'], true), fn (Builder $q) => $q->where('status', $status))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $currency = app(TenantContext::class)->companyOrFail()->currency;

        // Computed before wrapping in a resource collection, which replaces
        // the paginator's models with resource instances.
        $balances = $customers->getCollection()->mapWithKeys(fn (Customer $customer) => [
            $customer->id => MoneyResource::make(new Money(
                (int) $customer->getAttribute('receivable_total') - (int) $customer->getAttribute('receivable_paid'),
                $currency,
            )),
        ]);

        return Inertia::render('sales/customers/Index', [
            'customers' => CustomerResource::collection($customers),
            'balances' => $balances,
            'filters' => ['search' => $search, 'status' => $status],
        ]);
    }

    public function show(Request $request, Customer $customer, SaleIndexQuery $sales, TenantContext $tenant): Response
    {
        Gate::authorize('view', $customer);

        $currency = $tenant->companyOrFail()->currency;
        $money = fn (int $minor) => MoneyResource::make(new Money($minor, $currency));

        $completed = [SaleStatus::Confirmed->value, SaleStatus::PartiallyPaid->value, SaleStatus::Paid->value];
        $stats = Sale::query()
            ->where('customer_id', $customer->id)
            ->whereIn('status', $completed)
            ->selectRaw('COUNT(*) as orders, COALESCE(SUM(total), 0) as sold, MAX(sale_date) as last_sale')
            ->first();
        $receivable = Sale::query()
            ->where('customer_id', $customer->id)
            ->whereIn('status', SaleStatus::receivableValues())
            ->selectRaw('COALESCE(SUM(total - amount_paid), 0) as outstanding')
            ->value('outstanding');

        return Inertia::render('sales/customers/Show', [
            'customer' => CustomerResource::make($customer),
            'stats' => [
                'orders' => (int) $stats?->getAttribute('orders'),
                'sold' => $money((int) $stats?->getAttribute('sold')),
                'outstanding' => $money((int) $receivable),
                'last_sale' => $stats?->getAttribute('last_sale'),
            ],
            'sales' => SaleResource::collection($sales->paginate(['customer_id' => $customer->id], 10)),
            'notes' => $customer->notes()->with('author:id,name')->limit(50)->get()->map(fn (CustomerNote $note) => [
                'id' => $note->id,
                'body' => $note->body,
                'author' => $note->author?->name,
                'created_at' => $note->created_at?->toIso8601String(),
                'can_delete' => $request->user()?->can('deleteNote', [$customer, $note]) ?? false,
            ]),
        ]);
    }

    public function store(SaveCustomerRequest $request): RedirectResponse
    {
        $customer = Customer::create($request->validated());

        return back()->with('status', "Customer {$customer->name} created.");
    }

    public function update(SaveCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $customer->update($request->validated());

        return back()->with('status', "Customer {$customer->name} updated.");
    }

    public function destroy(Customer $customer, DeleteCustomer $delete): RedirectResponse
    {
        Gate::authorize('delete', $customer);

        $delete->handle($customer);

        return to_route('sales.customers.index')->with('status', "Customer {$customer->name} deleted.");
    }
}
