<?php

use App\Actions\Purchasing\ChangePurchaseOrderStatus;
use App\Actions\Purchasing\SavePurchaseOrder;
use App\DTOs\PurchaseOrderData;
use App\Enums\PurchaseOrderStatus;
use App\Enums\StockMovementType;
use App\Enums\SystemRole;
use App\Events\PurchaseOrderReceived;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReceipt;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    [$this->user, $this->company] = memberWithRole(SystemRole::Manager);
    actAsCompany($this->company);
    $this->actingAs($this->user);

    $this->warehouse = Warehouse::factory()->default()->create();
    $this->mouse = Product::factory()->create(['cost' => 1000]);
    $this->cable = Product::factory()->create(['cost' => 500]);

    // Approved order: 10 mice at 20.00 and 4 cables at 5.00.
    $order = app(SavePurchaseOrder::class)->handle(null, new PurchaseOrderData(
        supplierId: Supplier::factory()->create()->id,
        warehouseId: $this->warehouse->id,
        orderDate: '2026-09-29',
        expectedDate: null,
        notes: null,
        lines: [
            ['product_id' => $this->mouse->id, 'quantity' => 10, 'unit_cost' => 2000, 'tax_rate' => 1900],
            ['product_id' => $this->cable->id, 'quantity' => 4, 'unit_cost' => 500, 'tax_rate' => 0],
        ],
    ), $this->user);
    $status = app(ChangePurchaseOrderStatus::class);
    $status->submit($order);
    $this->order = $status->approve($order, $this->user)->load('items');

    [$this->mouseLine, $this->cableLine] = $this->order->items->all();
});

function receive(object $test, array $lines, array $extra = []): Illuminate\Testing\TestResponse
{
    return $test->post(route('purchasing.orders.receive', $test->order), [
        'lines' => collect($lines)->map(fn ($qty, $itemId) => ['item_id' => $itemId, 'quantity' => $qty])->values()->all(),
        ...$extra,
    ]);
}

it('receives partially, then completely', function () {
    receive($this, [$this->mouseLine->id => 6])->assertSessionHasNoErrors();

    $order = $this->order->fresh();
    expect($order->status)->toBe(PurchaseOrderStatus::PartiallyReceived)
        ->and($this->mouseLine->fresh()->received_quantity)->toBe(6);

    receive($this, [$this->mouseLine->id => 4, $this->cableLine->id => 4])->assertSessionHasNoErrors();

    $order = $order->fresh();
    expect($order->status)->toBe(PurchaseOrderStatus::Received)
        ->and($order->received_at)->not->toBeNull()
        ->and(PurchaseReceipt::pluck('number')->sort()->values()->all())->toBe(['GR-000001', 'GR-000002']);
});

it('adds stock through the ledger, referencing the receipt with its unit cost', function () {
    Event::fake([PurchaseOrderReceived::class]);

    receive($this, [$this->mouseLine->id => 6])->assertSessionHasNoErrors();

    $movement = StockMovement::sole();
    $receipt = PurchaseReceipt::sole();

    expect($movement->type)->toBe(StockMovementType::Purchase)
        ->and($movement->quantity)->toBe(6)
        ->and($movement->unit_cost)->toBe(2000)
        ->and($movement->getRawOriginal('reference_type'))->toBe('purchase_receipt')
        ->and($movement->reference_id)->toBe($receipt->id)
        ->and(StockLevel::where('product_id', $this->mouse->id)->value('quantity'))->toBe(6);

    Event::assertDispatched(PurchaseOrderReceived::class);
});

it('updates the product cost with the weighted average', function () {
    // 4 units already on hand at the current cost of 10.00
    app(App\Services\Inventory\InventoryService::class)->record(new App\DTOs\StockMovementData(
        $this->mouse, $this->warehouse, 4, StockMovementType::ManualIn,
    ));

    receive($this, [$this->mouseLine->id => 6])->assertSessionHasNoErrors();

    // (4 × 10.00 + 6 × 20.00) / 10 = 16.00
    expect($this->mouse->fresh()->cost)->toBe(1600);
});

it('never receives more than was ordered, and leaves no trace when it refuses', function () {
    receive($this, [$this->mouseLine->id => 8])->assertSessionHasNoErrors();

    receive($this, [$this->cableLine->id => 1, $this->mouseLine->id => 3])->assertSessionHasErrors('rule');

    expect($this->mouseLine->fresh()->received_quantity)->toBe(8)
        ->and($this->cableLine->fresh()->received_quantity)->toBe(0)
        ->and(PurchaseReceipt::count())->toBe(1)
        ->and(StockMovement::count())->toBe(1);
});

it('requires at least one positive quantity', function () {
    receive($this, [$this->mouseLine->id => 0])->assertSessionHasErrors('rule');
});

it('rejects lines that belong to another order', function () {
    $otherOrder = app(SavePurchaseOrder::class)->handle(null, new PurchaseOrderData(
        supplierId: Supplier::factory()->create()->id,
        warehouseId: $this->warehouse->id,
        orderDate: '2026-09-29', expectedDate: null, notes: null,
        lines: [['product_id' => $this->mouse->id, 'quantity' => 1, 'unit_cost' => 100, 'tax_rate' => 0]],
    ), $this->user);

    receive($this, [$otherOrder->items()->first()->id => 1])->assertSessionHasErrors('rule');
});

it('only receives approved orders', function () {
    $draft = app(SavePurchaseOrder::class)->handle(null, new PurchaseOrderData(
        supplierId: Supplier::factory()->create()->id,
        warehouseId: $this->warehouse->id,
        orderDate: '2026-09-29', expectedDate: null, notes: null,
        lines: [['product_id' => $this->mouse->id, 'quantity' => 1, 'unit_cost' => 100, 'tax_rate' => 0]],
    ), $this->user);

    $this->post(route('purchasing.orders.receive', $draft), [
        'lines' => [['item_id' => $draft->items()->first()->id, 'quantity' => 1]],
    ])->assertSessionHasErrors('rule');
});

it('can receive into a different warehouse', function () {
    $north = Warehouse::factory()->create();

    receive($this, [$this->cableLine->id => 2], ['warehouse_id' => $north->id])->assertSessionHasNoErrors();

    expect(StockLevel::where('warehouse_id', $north->id)->value('quantity'))->toBe(2)
        ->and(PurchaseReceipt::sole()->warehouse_id)->toBe($north->id);
});

it('cannot cancel an order once goods were received', function () {
    receive($this, [$this->mouseLine->id => 1]);

    $this->post(route('purchasing.orders.cancel', $this->order), ['reason' => 'Too late'])->assertSessionHasErrors('rule');
});

it('requires purchases.receive', function () {
    [$sales] = memberWithRole(SystemRole::Sales, $this->company);

    $this->actingAs($sales);
    receive($this, [$this->mouseLine->id => 1])->assertForbidden();

    expect(PurchaseOrder::find($this->order->id)->status)->toBe(PurchaseOrderStatus::Approved);
});
