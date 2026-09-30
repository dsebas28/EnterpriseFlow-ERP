<?php

use App\DTOs\StockMovementData;
use App\Enums\StockMovementType as Type;
use App\Enums\SystemRole;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryService;
use App\Services\Inventory\StockLedger;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    [, $this->company] = memberWithRole(SystemRole::Owner);
    actAsCompany($this->company);

    $this->product = Product::factory()->create();
    $this->north = Warehouse::factory()->create();
    $this->south = Warehouse::factory()->create();

    $inventory = app(InventoryService::class);
    $inventory->record(new StockMovementData($this->product, $this->north, 20, Type::Purchase));
    $inventory->record(new StockMovementData($this->product, $this->north, -5, Type::Sale));
    $inventory->record(new StockMovementData($this->product, $this->south, 7, Type::ManualIn));
});

it('finds no discrepancies when the projection matches the ledger', function () {
    expect(app(StockLedger::class)->discrepancies())->toBe([]);
});

it('detects and repairs a drifted projection', function () {
    // Simulate drift (e.g. a manual SQL fix gone wrong).
    DB::table('stock_levels')->where('warehouse_id', $this->north->id)->update(['quantity' => 999]);
    DB::table('stock_levels')->where('warehouse_id', $this->south->id)->delete();

    $issues = app(StockLedger::class)->discrepancies();

    expect($issues)->toHaveCount(2)
        ->and(collect($issues)->firstWhere('warehouse_id', $this->north->id))
        ->toMatchArray(['expected' => 15, 'actual' => 999]);

    expect(app(StockLedger::class)->rebuild())->toBe(2);

    expect(StockLevel::where('warehouse_id', $this->north->id)->value('quantity'))->toBe(15)
        ->and(StockLevel::where('warehouse_id', $this->south->id)->value('quantity'))->toBe(7)
        ->and(app(StockLedger::class)->discrepancies())->toBe([]);
});

it('reports drift from the command and fails on a dry run', function () {
    DB::table('stock_levels')->where('warehouse_id', $this->north->id)->update(['quantity' => 1]);
    tenant()->set(null);

    $this->artisan('inventory:rebuild')->assertFailed();

    $this->artisan('inventory:rebuild', ['--fix' => true])->assertSuccessful();

    $this->artisan('inventory:rebuild')
        ->expectsOutputToContain('stock levels match the ledger')
        ->assertSuccessful();
});

it('only inspects the requested company', function () {
    [, $other] = memberWithRole(SystemRole::Owner);
    DB::table('stock_levels')->where('warehouse_id', $this->north->id)->update(['quantity' => 1]);
    tenant()->set(null);

    $this->artisan('inventory:rebuild', ['--company' => $other->id])->assertSuccessful();
});
