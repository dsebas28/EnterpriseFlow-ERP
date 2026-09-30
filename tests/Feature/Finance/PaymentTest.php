<?php

use App\Actions\Invoicing\CreateInvoiceFromSale;
use App\Actions\Invoicing\IssueInvoice;
use App\Actions\Invoicing\RegisterSupplierBill;
use App\Actions\Payments\RecordPayment;
use App\Actions\Purchasing\ChangePurchaseOrderStatus;
use App\Actions\Purchasing\ReceivePurchaseOrder;
use App\Actions\Purchasing\SavePurchaseOrder;
use App\Actions\Sales\ConfirmSale;
use App\Actions\Sales\SaveSale;
use App\DTOs\PaymentData;
use App\DTOs\PurchaseOrderData;
use App\DTOs\SaleData;
use App\DTOs\StockMovementData;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SaleStatus;
use App\Enums\StockMovementType;
use App\Enums\SystemRole;
use App\Events\PaymentReceived;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Queue::fake(); // PDFs are irrelevant here
    $this->travelTo('2026-09-29 10:00:00');

    [$this->accountant, $this->company] = memberWithRole(SystemRole::Accountant);
    [$manager] = memberWithRole(SystemRole::Manager, $this->company);

    actAsCompany($this->company);
    $this->actingAs($manager);

    $warehouse = Warehouse::factory()->default()->create();
    $product = Product::factory()->create();
    app(InventoryService::class)->record(new StockMovementData($product, $warehouse, 5, StockMovementType::ManualIn));

    // Sale of 1000.00 (no tax) → issued invoice due in 30 days.
    $sale = app(SaveSale::class)->handle(null, new SaleData(Customer::factory()->create()->id, $warehouse->id, '2026-09-29', null, [
        ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100000, 'discount_rate' => 0, 'tax_rate' => 0],
    ]), $manager);
    app(ConfirmSale::class)->handle($sale, $manager);
    $invoice = app(CreateInvoiceFromSale::class)->handle($sale, '2026-10-29', null, $manager);
    $this->invoice = app(IssueInvoice::class)->handle($invoice, $manager);
    $this->sale = $sale;

    tenant()->set(null);
    auth()->logout();
});

function pay(object $test, string $amount, array $overrides = []): Illuminate\Testing\TestResponse
{
    return $test->actingAs($test->accountant)->post(route('finance.invoices.payments.store', $test->invoice), [
        'amount' => $amount,
        'method' => 'bank_transfer',
        'paid_at' => '2026-09-29',
        'reference' => 'TRX-1',
        ...$overrides,
    ]);
}

function fresh(object $test): array
{
    return tenant()->run($test->company, fn () => [$test->invoice->fresh(), $test->sale->fresh()]);
}

it('records a partial payment and updates the invoice and the sale', function () {
    Event::fake([PaymentReceived::class]);

    pay($this, '400.00')->assertSessionHasNoErrors();

    [$invoice, $sale] = fresh($this);
    expect($invoice->amount_paid)->toBe(40000)
        ->and($invoice->status)->toBe(InvoiceStatus::PartiallyPaid)
        ->and($invoice->balanceDue())->toBe(60000)
        ->and($sale->amount_paid)->toBe(40000)
        ->and($sale->status)->toBe(SaleStatus::PartiallyPaid);

    $payment = tenant()->run($this->company, fn () => Payment::sole());
    expect($payment->number)->toBe('PAY-000001')
        ->and($payment->method)->toBe(PaymentMethod::BankTransfer)
        ->and($payment->created_by)->toBe($this->accountant->id);

    Event::assertDispatched(PaymentReceived::class);
});

it('marks invoice and sale as paid when the balance reaches zero', function () {
    pay($this, '400');
    pay($this, '600')->assertSessionHasNoErrors();

    [$invoice, $sale] = fresh($this);
    expect($invoice->status)->toBe(InvoiceStatus::Paid)
        ->and($invoice->amount_paid)->toBe(100000)
        ->and($sale->status)->toBe(SaleStatus::Paid);
});

it('never accepts more than the balance due', function () {
    pay($this, '700');

    pay($this, '300.01')->assertSessionHasErrors('rule');

    expect(tenant()->run($this->company, fn () => Payment::count()))->toBe(1);
});

it('validates the payment input', function () {
    pay($this, '0', ['method' => 'crypto', 'paid_at' => '2026-12-31'])
        ->assertSessionHasErrors(['amount', 'method', 'paid_at']);

    pay($this, '10.001')->assertSessionHasErrors('amount');
});

it('refuses payments on drafts and cancelled invoices', function () {
    tenant()->run($this->company, fn () => $this->invoice->forceFill(['status' => InvoiceStatus::Cancelled])->save());

    pay($this, '10')->assertSessionHasErrors('rule');
});

it('keeps an overdue invoice overdue after a partial payment', function () {
    $this->travelTo('2026-11-05 10:00:00');
    $this->artisan('invoices:mark-overdue');

    pay($this, '100', ['paid_at' => '2026-11-05'])->assertSessionHasNoErrors();

    expect(fresh($this)[0]->status)->toBe(InvoiceStatus::Overdue);
});

it('voids a payment and recomputes balances and statuses', function () {
    pay($this, '1000');
    $payment = tenant()->run($this->company, fn () => Payment::sole());

    $this->actingAs($this->accountant)
        ->post(route('finance.payments.void', $payment), ['reason' => 'Bounced transfer'])
        ->assertSessionHasNoErrors();

    [$invoice, $sale] = fresh($this);
    $payment = tenant()->run($this->company, fn () => $payment->fresh());

    expect($payment->status)->toBe(PaymentStatus::Voided)
        ->and($payment->void_reason)->toBe('Bounced transfer')
        ->and($payment->voided_by)->toBe($this->accountant->id)
        ->and($invoice->amount_paid)->toBe(0)
        ->and($invoice->status)->toBe(InvoiceStatus::Issued)
        ->and($sale->amount_paid)->toBe(0)
        ->and($sale->status)->toBe(SaleStatus::Confirmed);

    // Voiding twice is refused.
    $this->actingAs($this->accountant)->post(route('finance.payments.void', $payment), ['reason' => 'again'])->assertSessionHasErrors('rule');
});

it('treats posted payments as immutable', function () {
    pay($this, '100');

    tenant()->run($this->company, function () {
        $payment = Payment::sole();

        expect(fn () => $payment->forceFill(['amount' => 1])->save())->toThrow(LogicException::class)
            ->and(fn () => $payment->delete())->toThrow(LogicException::class);
    });
});

it('pays supplier bills as outgoing payments', function () {
    [$bill, $manager] = tenant()->run($this->company, function () {
        [$manager] = memberWithRole(SystemRole::Manager, $this->company);
        $this->actingAs($manager);
        $order = app(SavePurchaseOrder::class)->handle(null, new PurchaseOrderData(
            Supplier::factory()->create()->id, Warehouse::first()->id, '2026-09-29', null, null,
            [['product_id' => Product::factory()->create()->id, 'quantity' => 2, 'unit_cost' => 5000, 'tax_rate' => 0]],
        ), $manager);
        app(ChangePurchaseOrderStatus::class)->submit($order);
        app(ChangePurchaseOrderStatus::class)->approve($order, $manager);
        app(ReceivePurchaseOrder::class)->handle($order, [$order->items()->first()->id => 2], $manager);

        return [app(RegisterSupplierBill::class)->handle($order, 'FV-9', '2026-09-29', '2026-10-29', [$order->items()->first()->id => 2], $manager), $manager];
    });

    $this->actingAs($this->accountant)
        ->post(route('finance.bills.payments.store', $bill), ['amount' => '100', 'method' => 'cash', 'paid_at' => '2026-09-29'])
        ->assertSessionHasNoErrors();

    tenant()->run($this->company, function () use ($bill) {
        $payment = Payment::where('supplier_bill_id', $bill->id)->sole();

        expect($payment->direction->value)->toBe('outgoing')
            ->and($bill->fresh()->status)->toBe(InvoiceStatus::Paid);
    });
});

it('lists payments with totals per direction', function () {
    pay($this, '250');
    pay($this, '150');

    $this->actingAs($this->accountant)
        ->get(route('finance.payments.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('finance/Payments')
            ->has('payments.data', 2)
            ->where('totals.incoming.amount', 40000)
            ->where('totals.outgoing.amount', 0));
});

it('requires payment permissions', function () {
    [$warehouseUser] = memberWithRole(SystemRole::Warehouse, $this->company);
    [$seller] = memberWithRole(SystemRole::Sales, $this->company);

    $this->actingAs($warehouseUser)->post(route('finance.invoices.payments.store', $this->invoice), [
        'amount' => '10', 'method' => 'cash', 'paid_at' => '2026-09-29',
    ])->assertForbidden();

    // Sales can collect payments but not void them.
    $this->actingAs($seller)->post(route('finance.invoices.payments.store', $this->invoice), [
        'amount' => '10', 'method' => 'cash', 'paid_at' => '2026-09-29',
    ])->assertSessionHasNoErrors();
    $payment = tenant()->run($this->company, fn () => Payment::sole());
    $this->actingAs($seller)->post(route('finance.payments.void', $payment), ['reason' => 'x'])->assertForbidden();
});

it('isolates payments between companies', function () {
    pay($this, '100');
    $payment = tenant()->run($this->company, fn () => Payment::sole());
    [$outsider] = memberWithRole(SystemRole::Owner);

    $this->actingAs($outsider)
        ->post(route('finance.invoices.payments.store', $this->invoice), ['amount' => '10', 'method' => 'cash', 'paid_at' => '2026-09-29'])
        ->assertNotFound();
    $this->actingAs($outsider)->post(route('finance.payments.void', $payment), ['reason' => 'x'])->assertNotFound();
    $this->actingAs($outsider)
        ->get(route('finance.payments.index'))
        ->assertInertia(fn (Assert $page) => $page->has('payments.data', 0));
});

it('cannot exceed the balance even when two payments race', function () {
    // Both requests read the same balance; the row lock in RecordPayment makes
    // the second one see the first payment and fail.
    tenant()->run($this->company, function () {
        $data = new PaymentData(amount: 80000, method: PaymentMethod::Cash, paidAt: '2026-09-29');
        app(RecordPayment::class)->handle($this->invoice, $data, $this->accountant);

        expect(fn () => app(RecordPayment::class)->handle($this->invoice, $data, $this->accountant))
            ->toThrow(App\Exceptions\BusinessRuleViolation::class);

        expect(Invoice::find($this->invoice->id)->amount_paid)->toBe(80000);
    });
});
