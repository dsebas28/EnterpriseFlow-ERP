<?php

use App\Actions\Invoicing\RegisterSupplierBill;
use App\Actions\Purchasing\ChangePurchaseOrderStatus;
use App\Actions\Purchasing\ReceivePurchaseOrder;
use App\Actions\Purchasing\SavePurchaseOrder;
use App\DTOs\PurchaseOrderData;
use App\Enums\InvoiceStatus;
use App\Enums\SystemRole;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\Warehouse;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo('2026-09-29 10:00:00');
    [$this->accountant, $this->company] = memberWithRole(SystemRole::Accountant);
    [$manager] = memberWithRole(SystemRole::Manager, $this->company);

    actAsCompany($this->company);
    $this->actingAs($manager);

    $this->supplier = Supplier::factory()->create();
    $order = app(SavePurchaseOrder::class)->handle(null, new PurchaseOrderData(
        supplierId: $this->supplier->id,
        warehouseId: Warehouse::factory()->default()->create()->id,
        orderDate: '2026-09-29',
        expectedDate: null,
        notes: null,
        lines: [
            ['product_id' => Product::factory()->create()->id, 'quantity' => 10, 'unit_cost' => 2000, 'tax_rate' => 1900],
            ['product_id' => Product::factory()->create()->id, 'quantity' => 5, 'unit_cost' => 1000, 'tax_rate' => 0],
        ],
    ), $manager);
    app(ChangePurchaseOrderStatus::class)->submit($order);
    app(ChangePurchaseOrderStatus::class)->approve($order, $manager);
    $order->load('items');
    [$this->lineA, $this->lineB] = $order->items->all();

    // Only 6 of line A and nothing of line B arrived so far.
    app(ReceivePurchaseOrder::class)->handle($order, [$this->lineA->id => 6], $manager);
    $this->order = $order->fresh();

    tenant()->set(null);
    auth()->logout();
});

function billPayload(object $test, array $lines, array $overrides = []): array
{
    return [
        'supplier_reference' => 'FV-1001',
        'bill_date' => '2026-09-29',
        'due_date' => '2026-10-29',
        'lines' => collect($lines)->map(fn ($qty, $id) => ['item_id' => $id, 'quantity' => $qty])->values()->all(),
        ...$overrides,
    ];
}

it('registers a supplier bill for received quantities at the agreed cost', function () {
    $this->actingAs($this->accountant)
        ->post(route('purchasing.orders.bills.store', $this->order), billPayload($this, [$this->lineA->id => 6]))
        ->assertRedirect();

    $bill = tenant()->run($this->company, fn () => SupplierBill::with('items')->sole());

    expect($bill->number)->toBe('BILL-000001')
        ->and($bill->status)->toBe(InvoiceStatus::Issued)
        ->and($bill->supplier_id)->toBe($this->supplier->id)
        // 6 × 20.00 = 120.00 (+19% = 22.80)
        ->and($bill->subtotal)->toBe(12000)
        ->and($bill->tax_total)->toBe(2280)
        ->and($bill->total)->toBe(14280)
        ->and($this->lineA->fresh()->billed_quantity)->toBe(6);
});

it('never bills more than was received and not yet billed', function () {
    $this->actingAs($this->accountant)
        ->post(route('purchasing.orders.bills.store', $this->order), billPayload($this, [$this->lineA->id => 7]))
        ->assertSessionHasErrors('rule');

    // Nothing of line B was received.
    $this->actingAs($this->accountant)
        ->post(route('purchasing.orders.bills.store', $this->order), billPayload($this, [$this->lineB->id => 1]))
        ->assertSessionHasErrors('rule');

    $this->actingAs($this->accountant)->post(route('purchasing.orders.bills.store', $this->order), billPayload($this, [$this->lineA->id => 4]));
    $this->actingAs($this->accountant)
        ->post(route('purchasing.orders.bills.store', $this->order), billPayload($this, [$this->lineA->id => 3], ['supplier_reference' => 'FV-1002']))
        ->assertSessionHasErrors('rule');

    expect(tenant()->run($this->company, fn () => SupplierBill::count()))->toBe(1);
});

it('rejects registering the same supplier invoice twice', function () {
    $this->actingAs($this->accountant)->post(route('purchasing.orders.bills.store', $this->order), billPayload($this, [$this->lineA->id => 3]));

    $this->actingAs($this->accountant)
        ->post(route('purchasing.orders.bills.store', $this->order), billPayload($this, [$this->lineA->id => 3]))
        ->assertSessionHasErrors('rule');
});

it('guarantees supplier reference uniqueness in the database', function () {
    $bill = tenant()->run($this->company, fn () => app(RegisterSupplierBill::class)->handle(
        $this->order, 'FV-1001', '2026-09-29', '2026-10-29', [$this->lineA->id => 1], $this->accountant,
    ));

    DB::table('supplier_bills')->insert([
        ...collect($bill->getAttributes())->except(['id', 'number', 'created_at', 'updated_at'])->all(),
        'id' => (string) Illuminate\Support\Str::ulid(),
        'number' => 'BILL-999999',
    ]);
})->throws(QueryException::class);

it('cancels a bill and releases its quantities', function () {
    $this->actingAs($this->accountant)->post(route('purchasing.orders.bills.store', $this->order), billPayload($this, [$this->lineA->id => 6]));
    $bill = tenant()->run($this->company, fn () => SupplierBill::sole());

    $this->actingAs($this->accountant)->post(route('finance.bills.cancel', $bill), ['reason' => 'Wrong amounts'])->assertSessionHasNoErrors();

    expect(tenant()->run($this->company, fn () => $bill->fresh()->status))->toBe(InvoiceStatus::Cancelled)
        ->and($this->lineA->fresh()->billed_quantity)->toBe(0);

    // The corrected bill can reuse the supplier reference.
    $this->actingAs($this->accountant)
        ->post(route('purchasing.orders.bills.store', $this->order), billPayload($this, [$this->lineA->id => 6]))
        ->assertSessionHasNoErrors();
});

it('marks overdue supplier bills', function () {
    tenant()->run($this->company, fn () => app(RegisterSupplierBill::class)->handle(
        $this->order, 'FV-1001', '2026-09-29', '2026-10-05', [$this->lineA->id => 1], $this->accountant,
    ));

    $this->travelTo('2026-10-06 08:00:00');
    $this->artisan('invoices:mark-overdue')->expectsOutputToContain('0 invoices and 1 supplier bills marked overdue')->assertSuccessful();
});

it('lists bills and exposes billable quantities on the purchase order', function () {
    $this->actingAs($this->accountant)->post(route('purchasing.orders.bills.store', $this->order), billPayload($this, [$this->lineA->id => 2]));

    $this->actingAs($this->accountant)
        ->get(route('finance.bills.index'))
        ->assertInertia(fn (Assert $page) => $page->component('finance/bills/Index')->has('bills.data', 1));

    [$manager] = memberWithRole(SystemRole::Owner, $this->company);
    $this->actingAs($manager)
        ->get(route('purchasing.orders.show', $this->order))
        ->assertInertia(fn (Assert $page) => $page
            ->where('order.data.items.0.billable_quantity', 4)
            ->has('order.data.bills', 1)
            ->where('can.bill', true));
});

it('isolates supplier bills between companies and requires permissions', function () {
    $bill = tenant()->run($this->company, fn () => app(RegisterSupplierBill::class)->handle(
        $this->order, 'FV-1001', '2026-09-29', '2026-10-29', [$this->lineA->id => 1], $this->accountant,
    ));
    [$outsider] = memberWithRole(SystemRole::Owner);
    [$warehouseUser] = memberWithRole(SystemRole::Warehouse, $this->company);

    $this->actingAs($outsider)->get(route('finance.bills.show', $bill))->assertNotFound();
    $this->actingAs($outsider)->post(route('finance.bills.cancel', $bill), ['reason' => 'x'])->assertNotFound();
    $this->actingAs($outsider)
        ->post(route('purchasing.orders.bills.store', $this->order), billPayload($this, [$this->lineA->id => 1], ['supplier_reference' => 'X']))
        ->assertNotFound();

    $this->actingAs($warehouseUser)
        ->post(route('purchasing.orders.bills.store', $this->order), billPayload($this, [$this->lineA->id => 1], ['supplier_reference' => 'Y']))
        ->assertForbidden();

    expect(tenant()->run($this->company, fn () => PurchaseOrder::find($this->order->id)->items()->first()->billed_quantity))->toBe(1);
});
