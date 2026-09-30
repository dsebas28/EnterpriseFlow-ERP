<?php

use App\Actions\Sales\SaveSale;
use App\DTOs\SaleData;
use App\DTOs\StockMovementData;
use App\Enums\SaleStatus;
use App\Enums\StockMovementType;
use App\Enums\SystemRole;
use App\Events\SaleConfirmed;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryService;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    [$this->manager, $this->company] = memberWithRole(SystemRole::Manager);
    actAsCompany($this->company);
    $this->actingAs($this->manager);

    $this->warehouse = Warehouse::factory()->default()->create();
    $this->chair = Product::factory()->create(['cost' => 4000]);
    $this->desk = Product::factory()->create(['cost' => 20000]);

    $stock = app(InventoryService::class);
    $stock->record(new StockMovementData($this->chair, $this->warehouse, 5, StockMovementType::ManualIn));
    $stock->record(new StockMovementData($this->desk, $this->warehouse, 1, StockMovementType::ManualIn));
});

function draftSale(object $test, int $chairs, int $desks = 0): Sale
{
    $lines = [['product_id' => $test->chair->id, 'quantity' => $chairs, 'unit_price' => 10000, 'discount_rate' => 0, 'tax_rate' => 1900]];
    if ($desks > 0) {
        $lines[] = ['product_id' => $test->desk->id, 'quantity' => $desks, 'unit_price' => 50000, 'discount_rate' => 0, 'tax_rate' => 0];
    }

    return app(SaveSale::class)->handle(
        null,
        new SaleData(Customer::factory()->create()->id, $test->warehouse->id, '2026-09-29', null, $lines),
        $test->manager,
    );
}

function onHandOf(Product $product): int
{
    return (int) StockLevel::where('product_id', $product->id)->sum('quantity');
}

it('deducts stock on confirmation and snapshots the unit cost', function () {
    Event::fake([SaleConfirmed::class]);
    $sale = draftSale($this, chairs: 3, desks: 1);

    $this->post(route('sales.orders.confirm', $sale))->assertSessionHasNoErrors();

    $sale = $sale->fresh('items');
    expect($sale->status)->toBe(SaleStatus::Confirmed)
        ->and($sale->confirmed_by)->toBe($this->manager->id)
        ->and(onHandOf($this->chair))->toBe(2)
        ->and(onHandOf($this->desk))->toBe(0)
        ->and($sale->items->pluck('unit_cost')->all())->toBe([4000, 20000]);

    $movements = StockMovement::where('type', StockMovementType::Sale)->get();
    expect($movements)->toHaveCount(2)
        ->and($movements->every(fn ($m) => $m->reference_type === 'sale' && $m->reference_id === $sale->id))->toBeTrue();

    Event::assertDispatched(SaleConfirmed::class);
});

it('rolls back the whole sale when one line lacks stock', function () {
    Event::fake([SaleConfirmed::class]);
    $sale = draftSale($this, chairs: 2, desks: 2); // only 1 desk available

    $this->post(route('sales.orders.confirm', $sale))->assertSessionHasErrors('rule');

    expect($sale->fresh()->status)->toBe(SaleStatus::Draft)
        ->and(onHandOf($this->chair))->toBe(5)
        ->and(onHandOf($this->desk))->toBe(1)
        ->and(StockMovement::where('type', StockMovementType::Sale)->count())->toBe(0)
        ->and($sale->fresh('items')->items->pluck('unit_cost')->filter()->all())->toBe([]);

    Event::assertNotDispatched(SaleConfirmed::class);
});

it('cannot be confirmed twice', function () {
    $sale = draftSale($this, chairs: 1);

    $this->post(route('sales.orders.confirm', $sale))->assertSessionHasNoErrors();
    $this->post(route('sales.orders.confirm', $sale))->assertSessionHasErrors('rule');

    expect(onHandOf($this->chair))->toBe(4);
});

it('restores stock with compensating return movements when a confirmed sale is cancelled', function () {
    $sale = draftSale($this, chairs: 3);
    $this->post(route('sales.orders.confirm', $sale));

    $this->post(route('sales.orders.cancel', $sale), ['reason' => 'Customer changed their mind'])->assertSessionHasNoErrors();

    $sale = $sale->fresh();
    expect($sale->status)->toBe(SaleStatus::Cancelled)
        ->and($sale->cancel_reason)->toBe('Customer changed their mind')
        ->and(onHandOf($this->chair))->toBe(5);

    // The ledger keeps both the sale and its reversal.
    $ledger = StockMovement::where('reference_id', $sale->id)->orderBy('id')->get();
    expect($ledger->pluck('type')->map->value->all())->toBe(['sale', 'return'])
        ->and($ledger->pluck('quantity')->all())->toBe([-3, 3])
        ->and($ledger[1]->unit_cost)->toBe(4000);
});

it('cancels drafts without touching stock', function () {
    $sale = draftSale($this, chairs: 1);

    $this->post(route('sales.orders.cancel', $sale), ['reason' => 'Duplicate'])->assertSessionHasNoErrors();

    expect(StockMovement::where('reference_id', $sale->id)->count())->toBe(0);
});

it('refuses to cancel a sale with payments', function () {
    $sale = draftSale($this, chairs: 1);
    $this->post(route('sales.orders.confirm', $sale));
    $sale->fresh()->forceFill(['amount_paid' => 100])->save();

    $this->post(route('sales.orders.cancel', $sale), ['reason' => 'Late'])->assertSessionHasErrors('rule');

    expect($sale->fresh()->status)->toBe(SaleStatus::Confirmed)
        ->and(onHandOf($this->chair))->toBe(4);
});

it('requires the confirm and cancel permissions', function () {
    [$employee] = memberWithRole(SystemRole::Employee, $this->company);
    [$seller] = memberWithRole(SystemRole::Sales, $this->company);
    $sale = draftSale($this, chairs: 1);

    $this->actingAs($employee)->post(route('sales.orders.confirm', $sale))->assertForbidden();

    $this->actingAs($seller)->post(route('sales.orders.confirm', $sale))->assertSessionHasNoErrors();
    $this->actingAs($seller)->post(route('sales.orders.cancel', $sale), ['reason' => 'x'])->assertForbidden();
});
