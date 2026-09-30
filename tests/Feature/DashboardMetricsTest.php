<?php

use App\Actions\Sales\ConfirmSale;
use App\Actions\Sales\SaveSale;
use App\DTOs\SaleData;
use App\DTOs\StockMovementData;
use App\Enums\StockMovementType;
use App\Enums\SystemRole;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\Dashboard\DashboardMetrics;
use App\Services\Inventory\InventoryService;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo('2026-09-29 12:00:00');
    [$this->owner, $this->company] = memberWithRole(SystemRole::Owner);

    tenant()->run($this->company, function () {
        $this->actingAs($this->owner);
        $warehouse = Warehouse::factory()->default()->create();
        $product = Product::factory()->create(['name' => 'Lamp', 'cost' => 1000, 'min_stock' => 20]);
        app(InventoryService::class)->record(new StockMovementData($product, $warehouse, 10, StockMovementType::ManualIn));

        foreach (['2026-09-29' => 2, '2026-09-10' => 3] as $date => $qty) {
            $sale = app(SaveSale::class)->handle(null, new SaleData(Customer::factory()->create()->id, $warehouse->id, $date, null, [
                ['product_id' => $product->id, 'quantity' => $qty, 'unit_price' => 2500, 'discount_rate' => 0, 'tax_rate' => 0],
            ]), $this->owner);
            app(ConfirmSale::class)->handle($sale, $this->owner);
        }
    });

    tenant()->set(null);
    auth()->logout();
});

it('shows real figures on the dashboard', function () {
    $this->actingAs($this->owner)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('sales.today.amount', 5000)
            ->where('sales.today_count', 1)
            ->where('sales.month.amount', 12500)
            ->where('sales.month_count', 2)
            ->has('sales.chart', DashboardMetrics::CHART_DAYS)
            ->where('sales.chart.29', ['date' => '2026-09-29', 'total' => 5000])
            ->where('sales.top_products.0.product', 'Lamp')
            ->where('finance.gross_profit_month.amount', 7500)
            ->where('inventory.low_stock_count', 1)
            ->has('sales.recent', 2));
});

it('only sends the sections the member is allowed to see', function () {
    [$employee] = memberWithRole(SystemRole::Employee, $this->company);
    [$warehouseUser] = memberWithRole(SystemRole::Warehouse, $this->company);

    $this->actingAs($employee)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->has('sales')->has('inventory')->missing('finance')->missing('purchases'));

    $this->actingAs($warehouseUser)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->has('inventory')->has('purchases')->missing('finance'));
});

it('never mixes figures of another company', function () {
    [$outsider] = memberWithRole(SystemRole::Owner);

    $this->actingAs($outsider)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('sales.month.amount', 0)
            ->where('sales.recent', [])
            ->where('inventory.low_stock_count', 0));
});

it('caches the figures per company for a short time', function () {
    tenant()->run($this->company, fn () => app(DashboardMetrics::class)->for($this->company));

    expect(cache()->has("dashboard:{$this->company->id}:2026-09-29"))->toBeTrue();
});
