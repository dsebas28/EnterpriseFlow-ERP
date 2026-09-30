<?php

use App\DTOs\StockMovementData;
use App\Enums\StockMovementType as Type;
use App\Enums\SystemRole;
use App\Models\Company;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryService;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Seed stock directly through the service, as the given company.
 */
function seedStock(Company $company, Product $product, Warehouse $warehouse, int $quantity): void
{
    tenant()->run($company, fn () => app(InventoryService::class)->record(
        new StockMovementData($product, $warehouse, $quantity, Type::ManualIn),
    ));
}

beforeEach(function () {
    [$this->user, $this->company] = memberWithRole(SystemRole::Warehouse);
    [$this->product, $this->main, $this->north] = tenant()->run($this->company, fn () => [
        Product::factory()->create(['name' => 'Cable', 'min_stock' => 10]),
        Warehouse::factory()->default()->create(['name' => 'Main']),
        Warehouse::factory()->create(['name' => 'North']),
    ]);
});

describe('stock page', function () {
    it('shows on-hand quantities, status and summary', function () {
        seedStock($this->company, $this->product, $this->main, 4);
        seedStock($this->company, $this->product, $this->north, 2);
        tenant()->run($this->company, fn () => Product::factory()->create(['name' => 'Empty', 'min_stock' => 1]));

        $this->actingAs($this->user)
            ->get(route('inventory.stock.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('inventory/Stock')
                ->where('items.data.0.name', 'Cable')
                ->where('items.data.0.on_hand', 6)
                ->where('items.data.0.status', 'low')
                ->where('items.data.1.status', 'out')
                ->where('summary.units', 6)
                ->where('summary.low', 1)
                ->where('summary.out', 1));
    });

    it('filters by warehouse and stock status', function () {
        seedStock($this->company, $this->product, $this->main, 4);
        tenant()->run($this->company, fn () => Product::factory()->create(['name' => 'Empty']));

        $this->actingAs($this->user)
            ->get(route('inventory.stock.index', ['warehouse_id' => $this->north->id, 'stock' => 'out']))
            ->assertInertia(fn (Assert $page) => $page->has('items.data', 2));

        $this->actingAs($this->user)
            ->get(route('inventory.stock.index', ['stock' => 'in']))
            ->assertInertia(fn (Assert $page) => $page->has('items.data', 1)->where('items.data.0.name', 'Cable'));
    });

    it('requires inventory.view', function () {
        [$accountant] = memberWithRole(SystemRole::Accountant);

        $this->actingAs($accountant)->get(route('inventory.stock.index'))->assertForbidden();
    });
});

describe('operations', function () {
    it('adjusts stock to a physical count', function () {
        seedStock($this->company, $this->product, $this->main, 10);

        $this->actingAs($this->user)
            ->post(route('inventory.adjustments.store'), [
                'product_id' => $this->product->id,
                'warehouse_id' => $this->main->id,
                'counted_quantity' => 7,
                'reason' => 'Cycle count',
            ])
            ->assertSessionHasNoErrors();

        $movement = tenant()->run($this->company, fn () => StockMovement::latest('id')->first());
        expect($movement->type)->toBe(Type::Adjustment)
            ->and($movement->quantity)->toBe(-3)
            ->and($movement->balance_after)->toBe(7)
            ->and($movement->notes)->toBe('Cycle count');
    });

    it('rejects an adjustment that changes nothing', function () {
        seedStock($this->company, $this->product, $this->main, 5);

        $this->actingAs($this->user)
            ->post(route('inventory.adjustments.store'), [
                'product_id' => $this->product->id, 'warehouse_id' => $this->main->id,
                'counted_quantity' => 5, 'reason' => 'Recount',
            ])
            ->assertSessionHasErrors('rule');
    });

    it('records manual entries with a unit cost and blocks exits beyond stock', function () {
        $this->actingAs($this->user)
            ->post(route('inventory.manual-movements.store'), [
                'product_id' => $this->product->id, 'warehouse_id' => $this->main->id,
                'type' => 'manual_in', 'quantity' => 8, 'unit_cost' => '1250.50', 'notes' => 'Opening balance',
            ])
            ->assertSessionHasNoErrors();

        expect(tenant()->run($this->company, fn () => StockMovement::sole()->unit_cost))->toBe(125050);

        $this->actingAs($this->user)
            ->post(route('inventory.manual-movements.store'), [
                'product_id' => $this->product->id, 'warehouse_id' => $this->main->id,
                'type' => 'manual_out', 'quantity' => 9, 'notes' => 'Breakage',
            ])
            ->assertSessionHasErrors('rule');

        expect(tenant()->run($this->company, fn () => StockMovement::count()))->toBe(1);
    });

    it('transfers stock between warehouses as two linked movements', function () {
        seedStock($this->company, $this->product, $this->main, 10);

        $this->actingAs($this->user)
            ->post(route('inventory.transfers.store'), [
                'product_id' => $this->product->id,
                'from_warehouse_id' => $this->main->id,
                'to_warehouse_id' => $this->north->id,
                'quantity' => 4,
            ])
            ->assertSessionHasNoErrors();

        tenant()->run($this->company, function () {
            $legs = StockMovement::where('type', Type::Transfer)->get();

            expect($legs)->toHaveCount(2)
                ->and($legs->pluck('transfer_id')->unique())->toHaveCount(1)
                ->and($legs->sum('quantity'))->toBe(0)
                ->and(StockLevel::where('warehouse_id', $this->main->id)->value('quantity'))->toBe(6)
                ->and(StockLevel::where('warehouse_id', $this->north->id)->value('quantity'))->toBe(4);
        });
    });

    it('does not transfer more than available or to the same warehouse', function () {
        seedStock($this->company, $this->product, $this->main, 2);

        $this->actingAs($this->user)
            ->post(route('inventory.transfers.store'), [
                'product_id' => $this->product->id, 'from_warehouse_id' => $this->main->id,
                'to_warehouse_id' => $this->north->id, 'quantity' => 3,
            ])
            ->assertSessionHasErrors('rule');

        $this->actingAs($this->user)
            ->post(route('inventory.transfers.store'), [
                'product_id' => $this->product->id, 'from_warehouse_id' => $this->main->id,
                'to_warehouse_id' => $this->main->id, 'quantity' => 1,
            ])
            ->assertSessionHasErrors('to_warehouse_id');

        expect(tenant()->run($this->company, fn () => StockMovement::where('type', Type::Transfer)->count()))->toBe(0);
    });

    it('refuses variable products', function () {
        $template = tenant()->run($this->company, fn () => Product::factory()->variable()->create());

        $this->actingAs($this->user)
            ->post(route('inventory.manual-movements.store'), [
                'product_id' => $template->id, 'warehouse_id' => $this->main->id,
                'type' => 'manual_in', 'quantity' => 1, 'notes' => 'x',
            ])
            ->assertSessionHasErrors('product_id');
    });

    it('requires the matching permission', function () {
        [$sales] = memberWithRole(SystemRole::Sales, $this->company);

        $this->actingAs($sales)
            ->post(route('inventory.adjustments.store'), [
                'product_id' => $this->product->id, 'warehouse_id' => $this->main->id,
                'counted_quantity' => 1, 'reason' => 'x',
            ])
            ->assertForbidden();
    });
});

describe('guards', function () {
    it('does not delete a product or warehouse that still holds stock', function () {
        [$manager] = memberWithRole(SystemRole::Manager, $this->company);
        seedStock($this->company, $this->product, $this->north, 1);

        $this->actingAs($manager)->delete(route('catalog.products.destroy', $this->product))->assertSessionHasErrors('rule');
        $this->actingAs($manager)->delete(route('inventory.warehouses.destroy', $this->north))->assertSessionHasErrors('rule');
    });
});

describe('tenant isolation', function () {
    it('cannot move stock of products or warehouses of another company', function () {
        [, $other] = memberWithRole(SystemRole::Owner);
        [$foreignProduct, $foreignWarehouse] = tenant()->run($other, fn () => [
            Product::factory()->create(),
            Warehouse::factory()->create(),
        ]);

        $this->actingAs($this->user)
            ->post(route('inventory.manual-movements.store'), [
                'product_id' => $foreignProduct->id, 'warehouse_id' => $foreignWarehouse->id,
                'type' => 'manual_in', 'quantity' => 5, 'notes' => 'x',
            ])
            ->assertSessionHasErrors(['product_id', 'warehouse_id']);

        expect(tenant()->run($other, fn () => StockMovement::count()))->toBe(0);
    });

    it('lists only the movements of the active company', function () {
        [, $other] = memberWithRole(SystemRole::Owner);
        [$foreignProduct, $foreignWarehouse] = tenant()->run($other, fn () => [Product::factory()->create(), Warehouse::factory()->create()]);
        seedStock($other, $foreignProduct, $foreignWarehouse, 3);
        seedStock($this->company, $this->product, $this->main, 1);

        $this->actingAs($this->user)
            ->get(route('inventory.movements.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('inventory/Movements')
                ->has('movements.data', 1)
                ->where('movements.data.0.product.id', $this->product->id));
    });
});
