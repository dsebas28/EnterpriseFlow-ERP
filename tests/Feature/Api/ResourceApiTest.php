<?php

use App\DTOs\StockMovementData;
use App\Enums\SaleStatus;
use App\Enums\StockMovementType;
use App\Enums\SystemRole;
use App\Models\Customer;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryService;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->manager, $this->company] = memberWithRole(SystemRole::Manager);

    [$this->warehouse, $this->customer, $this->supplier, $this->chair, $this->desk] = tenant()->run($this->company, function () {
        $warehouse = Warehouse::factory()->default()->create();
        $chair = Product::factory()->create(['name' => 'Chair', 'sku' => 'CH-1', 'price' => 10000, 'cost' => 4000]);
        $desk = Product::factory()->create(['name' => 'Desk', 'sku' => 'DK-1', 'price' => 50000, 'cost' => 20000]);

        app(InventoryService::class)->record(new StockMovementData($chair, $warehouse, 5, StockMovementType::ManualIn));

        return [$warehouse, Customer::factory()->create(), Supplier::factory()->create(), $chair, $desk];
    });

    Sanctum::actingAs($this->manager);
});

function apiSalePayload(object $test, array $overrides = []): array
{
    return [
        'customer_id' => $test->customer->id,
        'warehouse_id' => $test->warehouse->id,
        'sale_date' => '2026-09-29',
        'lines' => [
            ['product_id' => $test->chair->id, 'quantity' => 2, 'unit_price' => '100', 'discount_rate' => '10', 'tax_rate' => '19'],
        ],
        ...$overrides,
    ];
}

describe('products', function () {
    it('lists products with the envelope, pagination meta, search and sort', function () {
        $this->getJson('/api/v1/products?sort=-price&per_page=10')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.sku', 'DK-1')
            ->assertJsonPath('data.1.sku', 'CH-1')
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', 1)
            ->assertJsonPath('data.0.price.amount', 50000)
            ->assertJsonPath('data.0.price.decimal', '500.00');

        $this->getJson('/api/v1/products?search=chair')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Chair');
    });

    it('rejects unsupported page sizes', function () {
        $this->getJson('/api/v1/products?per_page=5000')
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonValidationErrors('per_page');
    });

    it('creates, updates and deletes a product', function () {
        $id = $this->postJson('/api/v1/products', [
            'type' => 'simple',
            'name' => 'Lamp',
            'sku' => 'lp-1',
            'cost' => '10',
            'price' => '25.50',
            'tax_rate' => '19',
            'min_stock' => 2,
            'status' => 'active',
        ])
            ->assertCreated()
            ->assertJsonPath('message', 'Product created successfully.')
            ->assertJsonPath('data.sku', 'LP-1')
            ->assertJsonPath('data.price.amount', 2550)
            ->json('data.id');

        $this->putJson("/api/v1/products/{$id}", [
            'name' => 'Desk lamp',
            'sku' => 'LP-1',
            'cost' => '10',
            'price' => '30',
            'tax_rate' => '19',
            'min_stock' => 2,
            'status' => 'active',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Desk lamp')
            ->assertJsonPath('data.price.amount', 3000);

        $this->deleteJson("/api/v1/products/{$id}")->assertOk()->assertJsonPath('data', null);
        $this->getJson("/api/v1/products/{$id}")->assertNotFound();
    });

    it('returns validation errors in the envelope', function () {
        $this->postJson('/api/v1/products', ['type' => 'simple'])
            ->assertUnprocessable()
            ->assertJsonStructure(['success', 'message', 'errors' => ['name', 'sku', 'price']]);
    });
});

describe('tenant isolation and authorization', function () {
    it('hides records of other companies behind a 404', function () {
        [, $other] = memberWithRole(SystemRole::Owner);
        $foreign = tenant()->run($other, fn () => Product::factory()->create());

        $this->getJson("/api/v1/products/{$foreign->id}")
            ->assertNotFound()
            ->assertExactJson(['success' => false, 'message' => 'Resource not found.', 'errors' => []]);

        $this->deleteJson("/api/v1/products/{$foreign->id}")->assertNotFound();
        expect(tenant()->run($other, fn () => Product::count()))->toBe(1);
    });

    it('selects the company from the X-Company-Id header', function () {
        [, $second] = memberWithRole(SystemRole::Owner, user: $this->manager);
        tenant()->run($second, fn () => Product::factory()->create(['sku' => 'SECOND-1']));

        $this->getJson('/api/v1/products', ['X-Company-Id' => $second->id])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.sku', 'SECOND-1');

        $this->getJson('/api/v1/products', ['X-Company-Id' => $this->company->id])
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    });

    it('refuses a company the user does not belong to', function () {
        [, $other] = memberWithRole(SystemRole::Owner);

        $this->getJson('/api/v1/products', ['X-Company-Id' => $other->id])
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'You do not have access to the requested company.');
    });

    it('answers 403 without the permission', function () {
        [$employee] = memberWithRole(SystemRole::Employee, $this->company);
        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/products')->assertOk();
        $this->postJson('/api/v1/products', ['name' => 'x'])
            ->assertForbidden()
            ->assertExactJson(['success' => false, 'message' => 'This action is not allowed.', 'errors' => []]);
        $this->getJson('/api/v1/purchases')->assertForbidden();
        $this->getJson('/api/v1/reports/sales')->assertForbidden();
    });
});

describe('customers', function () {
    it('creates, lists and shows customers', function () {
        $id = $this->postJson('/api/v1/customers', [
            'kind' => 'company',
            'name' => 'Acme Ltd',
            'tax_id' => '900123456',
            'email' => 'buy@acme.test',
            'status' => 'active',
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Acme Ltd')
            ->json('data.id');

        $this->getJson('/api/v1/customers?search=acme')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $id);

        $this->getJson("/api/v1/customers/{$id}")->assertOk()->assertJsonPath('data.tax_id', '900123456');
    });
});

describe('sales', function () {
    it('creates a draft with server-side totals and confirms it, deducting stock', function () {
        $id = $this->postJson('/api/v1/sales', apiSalePayload($this, ['total' => '1']))
            ->assertCreated()
            ->assertJsonPath('data.status', SaleStatus::Draft->value)
            ->assertJsonPath('data.total.amount', 21420)
            ->assertJsonPath('data.items.0.sku', 'CH-1')
            ->json('data.id');

        $this->postJson("/api/v1/sales/{$id}/confirm")
            ->assertOk()
            ->assertJsonPath('message', 'Sale confirmed.')
            ->assertJsonPath('data.status', SaleStatus::Confirmed->value);

        $this->getJson('/api/v1/inventory?search=CH-1')
            ->assertOk()
            ->assertJsonPath('data.0.on_hand', 3);
    });

    it('creates and confirms in a single call', function () {
        $this->postJson('/api/v1/sales', apiSalePayload($this, ['confirm' => true]))
            ->assertCreated()
            ->assertJsonPath('data.status', SaleStatus::Confirmed->value);
    });

    it('reports business rule violations as 422 without partial effects', function () {
        $id = $this->postJson('/api/v1/sales', apiSalePayload($this, [
            'lines' => [['product_id' => $this->chair->id, 'quantity' => 50, 'unit_price' => '100', 'discount_rate' => '0', 'tax_rate' => '0']],
        ]))->json('data.id');

        $this->postJson("/api/v1/sales/{$id}/confirm")
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['rule']]);

        expect(tenant()->run($this->company, fn () => Sale::find($id)->status))->toBe(SaleStatus::Draft);
    });

    it('cancels a confirmed sale and returns the stock', function () {
        $id = $this->postJson('/api/v1/sales', apiSalePayload($this, ['confirm' => true]))->json('data.id');

        $this->postJson("/api/v1/sales/{$id}/cancel")->assertJsonValidationErrors('reason');
        $this->postJson("/api/v1/sales/{$id}/cancel", ['reason' => 'Customer changed mind'])
            ->assertOk()
            ->assertJsonPath('data.status', SaleStatus::Cancelled->value);

        $this->getJson('/api/v1/inventory?search=CH-1')->assertJsonPath('data.0.on_hand', 5);
    });

    it('filters sales by status', function () {
        $this->postJson('/api/v1/sales', apiSalePayload($this))->assertCreated();
        $this->postJson('/api/v1/sales', apiSalePayload($this, ['confirm' => true]))->assertCreated();

        $this->getJson('/api/v1/sales?status=confirmed')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 1);

        $this->getJson('/api/v1/sales?status=bogus')->assertJsonValidationErrors('status');
    });
});

describe('purchases', function () {
    it('creates, lists and shows purchase orders', function () {
        $id = $this->postJson('/api/v1/purchases', [
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'order_date' => '2026-09-29',
            'expected_date' => '2026-10-05',
            'lines' => [['product_id' => $this->desk->id, 'quantity' => 2, 'unit_cost' => '150', 'tax_rate' => '0']],
        ])
            ->assertCreated()
            ->assertJsonPath('data.total.amount', 30000)
            ->json('data.id');

        $this->getJson('/api/v1/purchases')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson("/api/v1/purchases/{$id}")->assertOk()->assertJsonPath('data.items.0.quantity', 2);

        expect(tenant()->run($this->company, fn () => PurchaseOrder::count()))->toBe(1);
    });
});

describe('inventory and reports', function () {
    it('lists stock levels with filters', function () {
        $this->getJson('/api/v1/inventory?stock=out')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.sku', 'DK-1');

        $this->getJson('/api/v1/inventory?stock=sideways')->assertJsonValidationErrors('stock');
    });

    it('lists available reports and runs one by key', function () {
        $this->getJson('/api/v1/reports')
            ->assertOk()
            ->assertJsonFragment(['key' => 'sales-by-period']);

        $this->postJson('/api/v1/sales', apiSalePayload($this, ['confirm' => true]))->assertCreated();

        $this->getJson('/api/v1/reports/sales?from=2026-09-01&to=2026-09-30&group_by=month')
            ->assertOk()
            ->assertJsonPath('data.report', 'sales-by-period')
            ->assertJsonPath('data.currency', $this->company->currency)
            ->assertJsonStructure(['data' => ['columns', 'rows', 'totals', 'filters']]);

        $this->getJson('/api/v1/reports/low-stock')->assertOk();
        $this->getJson('/api/v1/reports/unknown')->assertNotFound();
        $this->getJson('/api/v1/reports/sales?from=2026-09-30&to=2026-09-01')->assertJsonValidationErrors('to');
    });
});
