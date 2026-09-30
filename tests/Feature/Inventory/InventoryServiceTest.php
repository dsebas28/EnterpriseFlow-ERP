<?php

use App\DTOs\StockMovementData;
use App\Enums\StockMovementType as Type;
use App\Enums\SystemRole;
use App\Events\StockFellBelowMinimum;
use App\Events\StockMovementRecorded;
use App\Exceptions\BusinessRuleViolation;
use App\Exceptions\InsufficientStock;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    [$this->user, $this->company] = memberWithRole(SystemRole::Warehouse);
    actAsCompany($this->company);
    $this->actingAs($this->user);

    $this->inventory = app(InventoryService::class);
    $this->product = Product::factory()->create(['min_stock' => 0]);
    $this->warehouse = Warehouse::factory()->create();
});

function move(Product $product, Warehouse $warehouse, int $quantity, Type $type = Type::Adjustment): StockMovement
{
    return app(InventoryService::class)->record(new StockMovementData($product, $warehouse, $quantity, $type));
}

function onHand(Product $product, Warehouse $warehouse): int
{
    return (int) StockLevel::where('product_id', $product->id)->where('warehouse_id', $warehouse->id)->value('quantity');
}

it('records a movement and updates the projection', function () {
    $movement = move($this->product, $this->warehouse, 10, Type::Purchase);

    expect($movement->balance_after)->toBe(10)
        ->and($movement->user_id)->toBe($this->user->id)
        ->and($movement->company_id)->toBe($this->company->id)
        ->and(onHand($this->product, $this->warehouse))->toBe(10);

    move($this->product, $this->warehouse, -3, Type::Sale);

    expect(onHand($this->product, $this->warehouse))->toBe(7)
        ->and(StockMovement::latest('id')->first()->balance_after)->toBe(7);
});

it('rejects taking more stock than available and leaves no trace', function () {
    move($this->product, $this->warehouse, 5, Type::Purchase);

    try {
        move($this->product, $this->warehouse, -6, Type::Sale);
        $this->fail('Expected InsufficientStock');
    } catch (InsufficientStock $e) {
        expect($e->available)->toBe(5)->and($e->requested)->toBe(6);
    }

    expect(onHand($this->product, $this->warehouse))->toBe(5)
        ->and(StockMovement::count())->toBe(1);
});

it('applies several movements atomically', function () {
    $other = Product::factory()->create();
    move($this->product, $this->warehouse, 5, Type::Purchase);

    expect(fn () => $this->inventory->recordMany([
        new StockMovementData($this->product, $this->warehouse, -2, Type::Sale),
        new StockMovementData($other, $this->warehouse, -1, Type::Sale), // no stock: fails
    ]))->toThrow(InsufficientStock::class);

    // The first line was rolled back together with the failing one.
    expect(onHand($this->product, $this->warehouse))->toBe(5)
        ->and(StockMovement::count())->toBe(1);
});

it('returns movements in the order given even though rows are locked in a fixed order', function () {
    $a = Product::factory()->create();
    $b = Product::factory()->create();

    $movements = $this->inventory->recordMany([
        new StockMovementData($b, $this->warehouse, 2, Type::Purchase),
        new StockMovementData($a, $this->warehouse, 1, Type::Purchase),
    ]);

    expect($movements[0]->product_id)->toBe($b->id)
        ->and($movements[1]->product_id)->toBe($a->id);
});

it('validates the sign of each movement type', function (Type $type, int $quantity) {
    move($this->product, $this->warehouse, $quantity, $type);
})->throws(BusinessRuleViolation::class)->with([
    'purchase out' => [Type::Purchase, -1],
    'sale in' => [Type::Sale, 1],
    'manual in negative' => [Type::ManualIn, -1],
    'zero' => [Type::Adjustment, 0],
]);

it('refuses stock for variable products and inactive warehouses', function () {
    $template = Product::factory()->variable()->create();
    expect(fn () => move($template, $this->warehouse, 1, Type::Purchase))->toThrow(BusinessRuleViolation::class);

    $closed = Warehouse::factory()->create(['is_active' => false]);
    expect(fn () => move($this->product, $closed, 1, Type::Purchase))->toThrow(BusinessRuleViolation::class);
});

it('allows negative stock when the company opts in', function () {
    $this->company->forceFill(['settings' => ['allow_negative_stock' => true]])->save();

    move($this->product, $this->warehouse, -4, Type::Sale);

    expect(onHand($this->product, $this->warehouse))->toBe(-4);
});

it('stores references with a stable morph alias, not a class name', function () {
    $movement = app(InventoryService::class)->record(new StockMovementData(
        $this->product, $this->warehouse, 1, Type::Purchase, reference: $this->product,
    ));

    expect($movement->getRawOriginal('reference_type'))->toBe('product')
        ->and($movement->reference->is($this->product))->toBeTrue();
});

describe('append-only ledger', function () {
    it('refuses updates and deletes through Eloquent', function () {
        $movement = move($this->product, $this->warehouse, 3, Type::Purchase);

        expect(fn () => $movement->forceFill(['quantity' => 300])->save())->toThrow(LogicException::class)
            ->and(fn () => $movement->delete())->toThrow(LogicException::class);
    });

    it('refuses updates and deletes at the database level', function () {
        $movement = move($this->product, $this->warehouse, 3, Type::Purchase);

        expect(fn () => DB::table('stock_movements')->where('id', $movement->id)->update(['quantity' => 300]))
            ->toThrow(QueryException::class, 'append-only')
            ->and(fn () => DB::table('stock_movements')->where('id', $movement->id)->delete())
            ->toThrow(QueryException::class, 'append-only');
    });
});

describe('events', function () {
    it('announces each recorded movement', function () {
        Event::fake([StockMovementRecorded::class]);

        move($this->product, $this->warehouse, 2, Type::Purchase);

        Event::assertDispatched(StockMovementRecorded::class, 1);
    });

    it('announces when total stock crosses below the minimum, once', function () {
        Event::fake([StockFellBelowMinimum::class]);
        $this->product->update(['min_stock' => 5]);

        move($this->product, $this->warehouse, 10, Type::Purchase); // 10: fine
        move($this->product, $this->warehouse, -6, Type::Sale);     // 4: crossed
        move($this->product, $this->warehouse, -1, Type::Sale);     // 3: still low, no new event

        Event::assertDispatchedTimes(StockFellBelowMinimum::class, 1);
        Event::assertDispatched(StockFellBelowMinimum::class, fn ($e) => $e->totalStock === 4);
    });

    it('does not announce movements that were rolled back', function () {
        Event::fake([StockMovementRecorded::class]);

        try {
            DB::transaction(function () {
                move($this->product, $this->warehouse, 2, Type::Purchase);
                throw new RuntimeException('document failed later');
            });
        } catch (RuntimeException) {
        }

        Event::assertNotDispatched(StockMovementRecorded::class);
    });
});
