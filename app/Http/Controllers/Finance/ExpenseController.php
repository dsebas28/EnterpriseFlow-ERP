<?php

namespace App\Http\Controllers\Finance;

use App\Actions\Expenses\ReviewExpense;
use App\Actions\Expenses\SaveExpense;
use App\Enums\ExpenseStatus;
use App\Enums\PartyStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\BusinessRuleViolation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\SaveExpenseRequest;
use App\Http\Resources\ExpenseResource;
use App\Http\Resources\MoneyResource;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Supplier;
use App\Support\Money\Currency;
use App\Support\Money\Money;
use App\Support\Tenancy\TenantContext;
use App\Support\Tenancy\TenantRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExpenseController extends Controller
{
    public function index(Request $request, TenantContext $tenant): Response
    {
        Gate::authorize('viewAny', Expense::class);

        $filters = $request->only(['search', 'status', 'category_id', 'from', 'to']);

        $query = Expense::query()
            ->when(trim((string) ($filters['search'] ?? '')), fn (Builder $q, string $s) => $q->where(fn (Builder $w) => $w
                ->whereLike('number', "%{$s}%")
                ->orWhereLike('description', "%{$s}%")))
            ->when(ExpenseStatus::tryFrom((string) ($filters['status'] ?? '')), fn (Builder $q, ExpenseStatus $s) => $q->where('status', $s->value))
            ->when($filters['category_id'] ?? null, fn (Builder $q, $id) => $q->where('category_id', (int) $id))
            ->when($filters['from'] ?? null, fn (Builder $q, string $from) => $q->whereDate('expense_date', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $q, string $to) => $q->whereDate('expense_date', '<=', $to));

        $approvedTotal = (int) (clone $query)->where('status', ExpenseStatus::Approved->value)->sum('amount');
        $pendingTotal = (int) (clone $query)->where('status', ExpenseStatus::Pending->value)->sum('amount');
        $company = $tenant->companyOrFail();

        return Inertia::render('finance/Expenses', [
            'expenses' => ExpenseResource::collection(
                $query->with(['category', 'supplier', 'creator', 'reviewer'])->orderByDesc('expense_date')->orderByDesc('number')->paginate(20)->withQueryString(),
            ),
            'totals' => [
                'approved' => MoneyResource::make(new Money($approvedTotal, $company->currency)),
                'pending' => MoneyResource::make(new Money($pendingTotal, $company->currency)),
            ],
            'categories' => ExpenseCategory::orderBy('name')->get(['id', 'name']),
            'suppliers' => Supplier::where('status', PartyStatus::Active->value)->orderBy('name')->get(['id', 'name']),
            'methods' => array_map(fn (PaymentMethod $m) => ['value' => $m->value, 'label' => $m->label()], PaymentMethod::cases()),
            'currency' => ['code' => $company->currency, 'decimals' => Currency::minorUnits($company->currency)],
            'today' => now($company->timezone)->toDateString(),
            'filters' => $filters,
            'can' => [
                'create' => $request->user()?->can('create', Expense::class) ?? false,
                'manageCategories' => $request->user()?->can('create', Expense::class) ?? false,
            ],
        ]);
    }

    public function store(SaveExpenseRequest $request, SaveExpense $save): RedirectResponse
    {
        $expense = $save->handle(null, $request->toData(), $request->file('receipt'), $request->user());

        return back()->with('status', "Expense {$expense->number} recorded and awaiting approval.");
    }

    public function update(SaveExpenseRequest $request, Expense $expense, SaveExpense $save): RedirectResponse
    {
        $save->handle($expense, $request->toData(), $request->file('receipt'), $request->user());

        return back()->with('status', "Expense {$expense->number} updated.");
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        Gate::authorize('delete', $expense);

        if (! $expense->isPending()) {
            throw new BusinessRuleViolation('Reviewed expenses are part of the books and cannot be deleted.');
        }

        $expense->delete();

        if ($expense->receipt_path) {
            Storage::disk(Expense::RECEIPT_DISK)->delete($expense->receipt_path);
        }

        return back()->with('status', "Expense {$expense->number} deleted.");
    }

    public function approve(Request $request, Expense $expense, ReviewExpense $review): RedirectResponse
    {
        Gate::authorize('review', $expense);

        $review->approve($expense, $request->user());

        return back()->with('status', "Expense {$expense->number} approved.");
    }

    public function reject(Request $request, Expense $expense, ReviewExpense $review): RedirectResponse
    {
        Gate::authorize('review', $expense);

        $validated = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $review->reject($expense, $request->user(), $validated['reason']);

        return back()->with('status', "Expense {$expense->number} rejected.");
    }

    public function receipt(Expense $expense): StreamedResponse
    {
        Gate::authorize('view', $expense);

        abort_unless($expense->receipt_path && Storage::disk(Expense::RECEIPT_DISK)->exists($expense->receipt_path), 404);

        return Storage::disk(Expense::RECEIPT_DISK)->download(
            $expense->receipt_path,
            "{$expense->number}.".pathinfo($expense->receipt_path, PATHINFO_EXTENSION),
        );
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        Gate::authorize('create', Expense::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', TenantRule::unique('expense_categories', 'name')],
        ]);

        ExpenseCategory::create($validated);

        return back()->with('status', "Category {$validated['name']} added.");
    }
}
