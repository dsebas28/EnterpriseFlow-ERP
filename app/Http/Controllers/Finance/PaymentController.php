<?php

namespace App\Http\Controllers\Finance;

use App\Actions\Payments\RecordPayment;
use App\Actions\Payments\VoidPayment;
use App\Enums\PaymentDirection;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\RecordPaymentRequest;
use App\Http\Resources\MoneyResource;
use App\Http\Resources\PaymentResource;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SupplierBill;
use App\Support\Money\Money;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    public function index(Request $request, TenantContext $tenant): Response
    {
        Gate::authorize('viewAny', Payment::class);

        $filters = $request->only(['direction', 'method', 'from', 'to', 'search']);

        $query = Payment::query()
            ->when(PaymentDirection::tryFrom((string) ($filters['direction'] ?? '')), fn (Builder $q, PaymentDirection $d) => $q->where('direction', $d->value))
            ->when(PaymentMethod::tryFrom((string) ($filters['method'] ?? '')), fn (Builder $q, PaymentMethod $m) => $q->where('method', $m->value))
            ->when($filters['from'] ?? null, fn (Builder $q, string $from) => $q->whereDate('paid_at', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $q, string $to) => $q->whereDate('paid_at', '<=', $to))
            ->when(trim((string) ($filters['search'] ?? '')), fn (Builder $q, string $search) => $q->where(fn (Builder $w) => $w
                ->whereLike('number', "%{$search}%")
                ->orWhereLike('reference', "%{$search}%")));

        // Totals of the filtered, non-voided payments.
        $totals = (clone $query)
            ->where('status', PaymentStatus::Posted->value)
            ->selectRaw('direction, SUM(amount) as total')
            ->groupBy('direction')
            ->pluck('total', 'direction');

        $currency = $tenant->companyOrFail()->currency;

        return Inertia::render('finance/Payments', [
            'payments' => PaymentResource::collection(
                $query->with(['invoice.customer:id,name', 'supplierBill.supplier:id,name', 'creator:id,name'])
                    ->orderByDesc('paid_at')
                    ->orderByDesc('number')
                    ->paginate(25)
                    ->withQueryString(),
            ),
            'totals' => [
                'incoming' => MoneyResource::make(new Money((int) ($totals[PaymentDirection::Incoming->value] ?? 0), $currency)),
                'outgoing' => MoneyResource::make(new Money((int) ($totals[PaymentDirection::Outgoing->value] ?? 0), $currency)),
            ],
            'filters' => $filters,
            'methods' => array_map(fn (PaymentMethod $m) => ['value' => $m->value, 'label' => $m->label()], PaymentMethod::cases()),
        ]);
    }

    public function storeForInvoice(RecordPaymentRequest $request, Invoice $invoice, RecordPayment $record): RedirectResponse
    {
        $payment = $record->handle($invoice, $request->toData(), $request->user());

        return back()->with('status', "Payment {$payment->number} recorded.");
    }

    public function storeForBill(RecordPaymentRequest $request, SupplierBill $bill, RecordPayment $record): RedirectResponse
    {
        $payment = $record->handle($bill, $request->toData(), $request->user());

        return back()->with('status', "Payment {$payment->number} recorded.");
    }

    public function void(Request $request, Payment $payment, VoidPayment $void): RedirectResponse
    {
        Gate::authorize('void', $payment);

        $validated = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        $void->handle($payment, $request->user(), $validated['reason']);

        return back()->with('status', "Payment {$payment->number} voided.");
    }
}
