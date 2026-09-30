<?php

use App\Enums\PurchaseOrderStatus;
use App\Enums\SystemRole;
use App\Events\PurchaseOrderApproved;
use App\Models\Company;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    [$this->manager, $this->company] = memberWithRole(SystemRole::Manager);
    [$this->supplier, $this->warehouse, $this->mouse, $this->cable] = tenant()->run($this->company, fn () => [
        Supplier::factory()->create(),
        Warehouse::factory()->default()->create(),
        Product::factory()->create(['name' => 'Mouse']),
        Product::factory()->create(['name' => 'Cable']),
    ]);
});

function orderPayload(object $test, array $overrides = []): array
{
    return [
        'supplier_id' => $test->supplier->id,
        'warehouse_id' => $test->warehouse->id,
        'order_date' => '2026-09-29',
        'expected_date' => '2026-10-05',
        'notes' => 'Q4 restock',
        'lines' => [
            ['product_id' => $test->mouse->id, 'quantity' => 3, 'unit_cost' => '33.33', 'tax_rate' => '19'],
            ['product_id' => $test->cable->id, 'quantity' => 10, 'unit_cost' => '5', 'tax_rate' => '0'],
        ],
        ...$overrides,
    ];
}

function createOrder(object $test, array $overrides = []): PurchaseOrder
{
    $test->actingAs($test->manager)->post(route('purchasing.orders.store'), orderPayload($test, $overrides))->assertSessionHasNoErrors();

    return tenant()->run($test->company, fn () => PurchaseOrder::latest('created_at')->latest('number')->first());
}

describe('drafting', function () {
    it('creates a numbered draft and computes totals on the server', function () {
        $this->actingAs($this->manager)
            ->post(route('purchasing.orders.store'), orderPayload($this, ['total' => '1', 'subtotal' => '1']))
            ->assertRedirect();

        $order = tenant()->run($this->company, fn () => PurchaseOrder::with('items')->sole());

        expect($order->number)->toBe('PO-000001')
            ->and($order->status)->toBe(PurchaseOrderStatus::Draft)
            ->and($order->currency)->toBe($this->company->currency)
            ->and($order->created_by)->toBe($this->manager->id)
            // 3 × 33.33 = 99.99 (+19% = 19.00) and 10 × 5.00 = 50.00
            ->and($order->subtotal)->toBe(14999)
            ->and($order->tax_total)->toBe(1900)
            ->and($order->total)->toBe(16899)
            ->and($order->items)->toHaveCount(2)
            ->and($order->items[0]->description)->toBe('Mouse');
    });

    it('validates lines and references', function () {
        $template = tenant()->run($this->company, fn () => Product::factory()->variable()->create());

        $this->actingAs($this->manager)
            ->post(route('purchasing.orders.store'), orderPayload($this, [
                'expected_date' => '2026-01-01',
                'lines' => [
                    ['product_id' => $template->id, 'quantity' => 0, 'unit_cost' => '1.234', 'tax_rate' => '101'],
                    ['product_id' => $this->mouse->id, 'quantity' => 1, 'unit_cost' => '1', 'tax_rate' => '0'],
                    ['product_id' => $this->mouse->id, 'quantity' => 1, 'unit_cost' => '1', 'tax_rate' => '0'],
                ],
            ]))
            ->assertSessionHasErrors([
                'expected_date', 'lines.0.product_id', 'lines.0.quantity', 'lines.0.unit_cost', 'lines.0.tax_rate', 'lines.2.product_id',
            ]);
    });

    it('rejects inactive suppliers', function () {
        $inactive = tenant()->run($this->company, fn () => Supplier::factory()->inactive()->create());

        $this->actingAs($this->manager)
            ->post(route('purchasing.orders.store'), orderPayload($this, ['supplier_id' => $inactive->id]))
            ->assertSessionHasErrors('supplier_id');
    });

    it('edits drafts only', function () {
        $order = createOrder($this);

        $this->actingAs($this->manager)
            ->put(route('purchasing.orders.update', $order), orderPayload($this, [
                'lines' => [['product_id' => $this->cable->id, 'quantity' => 1, 'unit_cost' => '7', 'tax_rate' => '0']],
            ]))
            ->assertSessionHasNoErrors();

        expect(tenant()->run($this->company, fn () => $order->fresh()->total))->toBe(700);

        $this->actingAs($this->manager)->post(route('purchasing.orders.submit', $order));

        $this->actingAs($this->manager)
            ->put(route('purchasing.orders.update', $order), orderPayload($this))
            ->assertSessionHasErrors('rule');
    });

    it('deletes drafts but not submitted orders', function () {
        $draft = createOrder($this);
        $submitted = createOrder($this);
        $this->actingAs($this->manager)->post(route('purchasing.orders.submit', $submitted));

        $this->actingAs($this->manager)->delete(route('purchasing.orders.destroy', $submitted))->assertSessionHasErrors('rule');
        $this->actingAs($this->manager)->delete(route('purchasing.orders.destroy', $draft))->assertRedirect(route('purchasing.orders.index'));

        expect(tenant()->run($this->company, fn () => PurchaseOrder::count()))->toBe(1);
    });
});

describe('workflow', function () {
    it('goes from draft to pending to approved, recording the approver', function () {
        Event::fake([PurchaseOrderApproved::class]);
        $order = createOrder($this);

        $this->actingAs($this->manager)->post(route('purchasing.orders.submit', $order))->assertSessionHasNoErrors();
        $this->actingAs($this->manager)->post(route('purchasing.orders.approve', $order))->assertSessionHasNoErrors();

        $order = tenant()->run($this->company, fn () => $order->fresh());
        expect($order->status)->toBe(PurchaseOrderStatus::Approved)
            ->and($order->approved_by)->toBe($this->manager->id)
            ->and($order->approved_at)->not->toBeNull();

        Event::assertDispatched(PurchaseOrderApproved::class);
    });

    it('rejects invalid transitions', function () {
        $order = createOrder($this);

        // A draft cannot be approved without being submitted first.
        $this->actingAs($this->manager)->post(route('purchasing.orders.approve', $order))->assertSessionHasErrors('rule');

        expect(tenant()->run($this->company, fn () => $order->fresh()->status))->toBe(PurchaseOrderStatus::Draft);
    });

    it('lets an approver send a pending order back to draft', function () {
        $order = createOrder($this);
        $this->actingAs($this->manager)->post(route('purchasing.orders.submit', $order));

        $this->actingAs($this->manager)->post(route('purchasing.orders.return-to-draft', $order))->assertSessionHasNoErrors();

        expect(tenant()->run($this->company, fn () => $order->fresh()->status))->toBe(PurchaseOrderStatus::Draft);
    });

    it('requires purchases.approve to approve', function () {
        $order = createOrder($this);
        $this->actingAs($this->manager)->post(route('purchasing.orders.submit', $order));
        [$warehouseUser] = memberWithRole(SystemRole::Warehouse, $this->company);

        $this->actingAs($warehouseUser)->post(route('purchasing.orders.approve', $order))->assertForbidden();
    });

    it('cancels with a mandatory reason', function () {
        $order = createOrder($this);

        $this->actingAs($this->manager)->post(route('purchasing.orders.cancel', $order), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->actingAs($this->manager)->post(route('purchasing.orders.cancel', $order), ['reason' => 'Supplier out of stock'])->assertSessionHasNoErrors();

        $order = tenant()->run($this->company, fn () => $order->fresh());
        expect($order->status)->toBe(PurchaseOrderStatus::Cancelled)
            ->and($order->cancel_reason)->toBe('Supplier out of stock')
            ->and($order->cancelled_by)->toBe($this->manager->id);

        // Terminal state.
        $this->actingAs($this->manager)->post(route('purchasing.orders.submit', $order))->assertSessionHasErrors('rule');
    });

    it('shows the order with the actions allowed for the member', function () {
        $order = createOrder($this);

        $this->actingAs($this->manager)
            ->get(route('purchasing.orders.show', $order))
            ->assertInertia(fn (Assert $page) => $page
                ->component('purchasing/orders/Show')
                ->where('order.data.number', 'PO-000001')
                ->where('order.data.total.amount', 16899)
                ->where('can.submit', true)
                ->where('can.approve', false)
                ->where('can.receive', false));
    });
});

describe('listing and isolation', function () {
    it('lists orders filtered by status', function () {
        createOrder($this);
        $submitted = createOrder($this);
        $this->actingAs($this->manager)->post(route('purchasing.orders.submit', $submitted));

        $this->actingAs($this->manager)
            ->get(route('purchasing.orders.index', ['status' => 'pending']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('purchasing/orders/Index')
                ->has('orders.data', 1)
                ->where('orders.data.0.number', $submitted->number));
    });

    it('company A cannot see or act on purchase orders of company B', function () {
        $order = createOrder($this);
        [$intruder] = memberWithRole(SystemRole::Owner);

        $this->actingAs($intruder)->get(route('purchasing.orders.show', $order))->assertNotFound();
        $this->actingAs($intruder)->post(route('purchasing.orders.submit', $order))->assertNotFound();
        $this->actingAs($intruder)->put(route('purchasing.orders.update', $order), orderPayload($this))->assertNotFound();
        $this->actingAs($intruder)->delete(route('purchasing.orders.destroy', $order))->assertNotFound();
    });

    it('cannot order from suppliers or products of another company', function () {
        /** @var Company $other */
        [, $other] = memberWithRole(SystemRole::Owner);
        [$foreignSupplier, $foreignProduct] = tenant()->run($other, fn () => [Supplier::factory()->create(), Product::factory()->create()]);

        $this->actingAs($this->manager)
            ->post(route('purchasing.orders.store'), orderPayload($this, [
                'supplier_id' => $foreignSupplier->id,
                'lines' => [['product_id' => $foreignProduct->id, 'quantity' => 1, 'unit_cost' => '1', 'tax_rate' => '0']],
            ]))
            ->assertSessionHasErrors(['supplier_id', 'lines.0.product_id']);
    });
});
