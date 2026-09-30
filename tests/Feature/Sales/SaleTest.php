<?php

use App\Enums\SaleStatus;
use App\Enums\SystemRole;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Warehouse;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    [$this->seller, $this->company] = memberWithRole(SystemRole::Sales);
    [$this->customer, $this->warehouse, $this->chair, $this->desk] = tenant()->run($this->company, fn () => [
        Customer::factory()->create(),
        Warehouse::factory()->default()->create(),
        Product::factory()->create(['name' => 'Chair', 'price' => 10000]),
        Product::factory()->create(['name' => 'Desk', 'price' => 50000]),
    ]);
});

function salePayload(object $test, array $overrides = []): array
{
    return [
        'customer_id' => $test->customer->id,
        'warehouse_id' => $test->warehouse->id,
        'sale_date' => '2026-09-29',
        'notes' => null,
        'lines' => [
            // 2 × 100.00, 10% off = 180.00, +19% = 34.20
            ['product_id' => $test->chair->id, 'quantity' => 2, 'unit_price' => '100', 'discount_rate' => '10', 'tax_rate' => '19'],
            // 1 × 500.00, no discount, no tax
            ['product_id' => $test->desk->id, 'quantity' => 1, 'unit_price' => '500', 'discount_rate' => '0', 'tax_rate' => '0'],
        ],
        ...$overrides,
    ];
}

function createSale(object $test, array $overrides = []): Sale
{
    $test->actingAs($test->seller)->post(route('sales.orders.store'), salePayload($test, $overrides))->assertSessionHasNoErrors();

    return tenant()->run($test->company, fn () => Sale::latest('number')->first());
}

it('creates a numbered draft with discounts and taxes computed on the server', function () {
    $this->actingAs($this->seller)
        ->post(route('sales.orders.store'), salePayload($this, ['total' => '1']))
        ->assertRedirect();

    $sale = tenant()->run($this->company, fn () => Sale::with('items')->sole());

    expect($sale->number)->toBe('SO-000001')
        ->and($sale->status)->toBe(SaleStatus::Draft)
        ->and($sale->discount_total)->toBe(2000)
        ->and($sale->subtotal)->toBe(68000)
        ->and($sale->tax_total)->toBe(3420)
        ->and($sale->total)->toBe(71420)
        ->and($sale->items[0]->line_total)->toBe(21420)
        ->and($sale->items[0]->unit_cost)->toBeNull();
});

it('validates lines, products and customers', function () {
    $inactiveProduct = tenant()->run($this->company, fn () => Product::factory()->inactive()->create());
    $inactiveCustomer = tenant()->run($this->company, fn () => Customer::factory()->inactive()->create());

    $this->actingAs($this->seller)
        ->post(route('sales.orders.store'), salePayload($this, [
            'customer_id' => $inactiveCustomer->id,
            'lines' => [
                ['product_id' => $inactiveProduct->id, 'quantity' => 1, 'unit_price' => '1', 'tax_rate' => '0'],
                ['product_id' => $this->chair->id, 'quantity' => 1, 'unit_price' => '1', 'discount_rate' => '120', 'tax_rate' => '0'],
            ],
        ]))
        ->assertSessionHasErrors(['customer_id', 'lines.0.product_id', 'lines.1.discount_rate']);
});

it('edits and deletes drafts only', function () {
    $sale = createSale($this);

    $this->actingAs($this->seller)
        ->put(route('sales.orders.update', $sale), salePayload($this, [
            'lines' => [['product_id' => $this->desk->id, 'quantity' => 2, 'unit_price' => '450', 'discount_rate' => '0', 'tax_rate' => '0']],
        ]))
        ->assertSessionHasNoErrors();

    expect(tenant()->run($this->company, fn () => $sale->fresh()->total))->toBe(90000);

    $this->actingAs($this->seller)->post(route('sales.orders.pending', $sale))->assertSessionHasNoErrors();

    $this->actingAs($this->seller)->put(route('sales.orders.update', $sale), salePayload($this))->assertSessionHasErrors('rule');
    $this->actingAs($this->seller)->delete(route('sales.orders.destroy', $sale))->assertSessionHasErrors('rule');

    $this->actingAs($this->seller)->post(route('sales.orders.return-to-draft', $sale))->assertSessionHasNoErrors();
    $this->actingAs($this->seller)->delete(route('sales.orders.destroy', $sale))->assertRedirect(route('sales.orders.index'));
});

it('only allows the documented sale transitions', function () {
    $allowed = [
        'draft' => ['pending', 'confirmed', 'cancelled'],
        'pending' => ['confirmed', 'draft', 'cancelled'],
        'confirmed' => ['partially_paid', 'paid', 'cancelled'],
        'partially_paid' => ['partially_paid', 'paid'],
        'paid' => [],
        'cancelled' => [],
    ];

    foreach (SaleStatus::cases() as $from) {
        foreach (SaleStatus::cases() as $to) {
            expect($from->canTransitionTo($to))->toBe(in_array($to->value, $allowed[$from->value], true));
        }
    }
});

it('lists sales filtered by status and shows allowed actions', function () {
    $draft = createSale($this);

    $this->actingAs($this->seller)
        ->get(route('sales.orders.index', ['status' => 'draft']))
        ->assertInertia(fn (Assert $page) => $page->component('sales/orders/Index')->has('sales.data', 1));

    $this->actingAs($this->seller)
        ->get(route('sales.orders.show', $draft))
        ->assertInertia(fn (Assert $page) => $page
            ->component('sales/orders/Show')
            ->where('sale.data.number', 'SO-000001')
            ->where('can.confirm', true)
            // The Sales role cannot cancel.
            ->where('can.cancel', false));
});

it('company A cannot see or modify sales of company B', function () {
    $sale = createSale($this);
    [$intruder] = memberWithRole(SystemRole::Owner);

    $this->actingAs($intruder)->get(route('sales.orders.show', $sale))->assertNotFound();
    $this->actingAs($intruder)->put(route('sales.orders.update', $sale), salePayload($this))->assertNotFound();
    $this->actingAs($intruder)->post(route('sales.orders.confirm', $sale))->assertNotFound();
    $this->actingAs($intruder)->post(route('sales.orders.cancel', $sale), ['reason' => 'x'])->assertNotFound();

    expect(tenant()->run($this->company, fn () => $sale->fresh()->status))->toBe(SaleStatus::Draft);
});

it('cannot sell products or to customers of another company', function () {
    [, $other] = memberWithRole(SystemRole::Owner);
    [$foreignCustomer, $foreignProduct] = tenant()->run($other, fn () => [Customer::factory()->create(), Product::factory()->create()]);

    $this->actingAs($this->seller)
        ->post(route('sales.orders.store'), salePayload($this, [
            'customer_id' => $foreignCustomer->id,
            'lines' => [['product_id' => $foreignProduct->id, 'quantity' => 1, 'unit_price' => '1', 'tax_rate' => '0']],
        ]))
        ->assertSessionHasErrors(['customer_id', 'lines.0.product_id']);
});
