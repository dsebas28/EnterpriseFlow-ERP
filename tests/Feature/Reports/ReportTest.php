<?php

use App\Actions\Invoicing\CreateInvoiceFromSale;
use App\Actions\Invoicing\IssueInvoice;
use App\Actions\Payments\RecordPayment;
use App\Actions\Sales\ConfirmSale;
use App\Actions\Sales\SaveSale;
use App\DTOs\PaymentData;
use App\DTOs\SaleData;
use App\DTOs\StockMovementData;
use App\Enums\PaymentMethod;
use App\Enums\StockMovementType;
use App\Enums\SystemRole;
use App\Models\Category;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Product;
use App\Models\ReportExport;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryService;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Builds a small but complete set of transactions for one company.
 *
 * @return array<string, mixed>
 */
function reportScenario(Company $company, User $manager): array
{
    return tenant()->run($company, function () use ($company, $manager) {
        test()->actingAs($manager);
        $warehouse = Warehouse::factory()->default()->create();
        $chairs = Category::factory()->create(['name' => 'Chairs']);
        $chair = Product::factory()->create(['name' => 'Chair', 'sku' => 'CH-1', 'cost' => 1000, 'category_id' => $chairs->id, 'min_stock' => 0]);
        $desk = Product::factory()->create(['name' => 'Desk', 'sku' => 'DK-1', 'cost' => 2000, 'min_stock' => 10]);

        $stock = app(InventoryService::class);
        $stock->record(new StockMovementData($chair, $warehouse, 10, StockMovementType::ManualIn));
        $stock->record(new StockMovementData($desk, $warehouse, 5, StockMovementType::ManualIn));

        $acme = Customer::factory()->create(['name' => '=HYPERLINK("http://evil.test")']);
        $globex = Customer::factory()->create(['name' => 'Globex']);

        $sell = function (Customer $customer, string $date, array $lines, bool $confirm = true) use ($warehouse, $manager) {
            $sale = app(SaveSale::class)->handle(null, new SaleData($customer->id, $warehouse->id, $date, null, $lines), $manager);

            return $confirm ? app(ConfirmSale::class)->handle($sale, $manager) : $sale;
        };

        // September (the current month when tests run on 2026-09-29).
        $s1 = $sell($acme, '2026-09-10', [['product_id' => $chair->id, 'quantity' => 2, 'unit_price' => 3000, 'discount_rate' => 0, 'tax_rate' => 1900]]);
        $s2 = $sell($globex, '2026-09-20', [['product_id' => $desk->id, 'quantity' => 1, 'unit_price' => 5000, 'discount_rate' => 0, 'tax_rate' => 0]]);
        // Draft: never counts. August: outside the default period.
        $sell($globex, '2026-09-21', [['product_id' => $chair->id, 'quantity' => 1, 'unit_price' => 3000, 'discount_rate' => 0, 'tax_rate' => 0]], confirm: false);
        $sell($globex, '2026-08-15', [['product_id' => $chair->id, 'quantity' => 1, 'unit_price' => 3000, 'discount_rate' => 0, 'tax_rate' => 0]]);

        $rent = ExpenseCategory::create(['name' => 'Rent']);
        foreach ([['approved', 1500], ['pending', 999]] as [$status, $amount]) {
            $expense = new Expense;
            $expense->forceFill([
                'number' => 'EXP-'.$status, 'category_id' => $rent->id, 'description' => 'Office', 'amount' => $amount,
                'currency' => $company->currency, 'expense_date' => '2026-09-15', 'status' => $status,
            ])->save();
        }

        // Receivables: S1 not yet due; S2 was due on Aug 1 (59 days late) and is partly paid.
        $issue = fn ($sale) => app(IssueInvoice::class)->handle(app(CreateInvoiceFromSale::class)->handle($sale, '2026-10-15', null, $manager), $manager);
        $issue($s1);
        $late = $issue($s2);
        $late->forceFill(['due_date' => '2026-08-01'])->save();
        app(RecordPayment::class)->handle($late, new PaymentData(1000, PaymentMethod::Cash, '2026-09-25'), $manager);

        return compact('warehouse', 'chair', 'desk', 'chairs', 'acme', 'globex');
    });
}

beforeEach(function () {
    Queue::fake([App\Jobs\GenerateInvoicePdf::class]);
    Storage::fake('local');
    $this->travelTo('2026-09-29 12:00:00');

    [$this->manager, $this->company] = memberWithRole(SystemRole::Manager);
    [$this->accountant] = memberWithRole(SystemRole::Accountant, $this->company);
    $this->data = reportScenario($this->company, $this->manager);

    // A second company with its own sale that must never leak into reports.
    [$otherManager, $this->other] = memberWithRole(SystemRole::Manager);
    reportScenario($this->other, $otherManager);

    tenant()->set(null);
    auth()->logout();
});

function runReport(object $test, string $key, array $filters = [], ?User $as = null): array
{
    $props = [];
    $test->actingAs($as ?? $test->accountant)
        ->get(route('reports.show', ['key' => $key, ...$filters]))
        ->assertOk()
        ->assertInertia(function (Assert $page) use (&$props) {
            $props = $page->toArray()['props'];
        });

    return $props;
}

it('lists the report catalogue', function () {
    $this->actingAs($this->accountant)
        ->get(route('reports.index'))
        ->assertInertia(fn (Assert $page) => $page->component('reports/Index')->has('reports.Sales', 3)->has('reports.Finance', 4));
});

it('reports sales by period for the current month by default, excluding drafts', function () {
    $props = runReport($this, 'sales-by-period', ['group_by' => 'month']);

    expect($props['rows'])->toBe([
        ['period' => '2026-09', 'orders' => 2, 'discounts' => 0, 'net' => 11000, 'tax' => 1140, 'total' => 12140],
    ]);
});

it('widens the period and groups by day', function () {
    $props = runReport($this, 'sales-by-period', ['from' => '2026-08-01', 'to' => '2026-09-30', 'group_by' => 'day']);

    expect(collect($props['rows'])->pluck('period')->all())->toBe(['2026-08-15', '2026-09-10', '2026-09-20'])
        ->and($props['totals']['total'])->toBe(15140);
});

it('reports margin per product using the cost captured at sale time', function () {
    // Changing today's cost must not rewrite history.
    tenant()->run($this->company, fn () => $this->data['chair']->forceFill(['cost' => 9999])->save());

    $rows = runReport($this, 'sales-by-product')['rows'];

    // toEqual: JSON turns 60.0 into 60.
    expect($rows)->toEqual([
        ['sku' => 'CH-1', 'product' => 'Chair', 'quantity' => 2, 'revenue' => 6000, 'cost' => 2000, 'margin' => 4000, 'margin_pct' => 66.7],
        ['sku' => 'DK-1', 'product' => 'Desk', 'quantity' => 1, 'revenue' => 5000, 'cost' => 2000, 'margin' => 3000, 'margin_pct' => 60.0],
    ]);

    // Category filter.
    expect(runReport($this, 'sales-by-product', ['category_id' => $this->data['chairs']->id])['rows'])->toHaveCount(1);
});

it('computes profit and loss', function () {
    $props = runReport($this, 'profit', ['group_by' => 'month']);

    expect($props['rows'])->toEqual([[
        'period' => '2026-09', 'revenue' => 11000, 'cogs' => 4000, 'gross_profit' => 7000,
        'expenses' => 1500, 'net_profit' => 5500, 'net_margin' => 50.0,
    ]]);
});

it('reports expenses by category with only approved expenses', function () {
    expect(runReport($this, 'expenses-by-category')['rows'])->toEqual([
        ['category' => 'Rent', 'count' => 1, 'total' => 1500, 'share' => 100.0],
    ]);
});

it('ages receivables into buckets by days past due', function () {
    $rows = collect(runReport($this, 'receivables-aging')['rows'])->keyBy('party');

    expect($rows['Globex'])->toMatchArray(['current' => 0, 'd31_60' => 4000, 'total' => 4000])
        ->and($rows[$this->data['acme']->name])->toMatchArray(['current' => 7140, 'total' => 7140]);
});

it('values inventory and lists low stock', function () {
    $rows = collect(runReport($this, 'inventory-valuation')['rows'])->keyBy('sku');

    // Chair: 10 − 2 (Sep) − 1 (Aug) = 7 × 10.00; Desk: 5 − 1 = 4 × 20.00
    expect($rows['CH-1'])->toMatchArray(['on_hand' => 7, 'value' => 7000])
        ->and($rows['DK-1'])->toMatchArray(['on_hand' => 4, 'value' => 8000]);

    expect(runReport($this, 'low-stock')['rows'])->toBe([
        ['sku' => 'DK-1', 'product' => 'Desk', 'on_hand' => 4, 'min_stock' => 10, 'shortfall' => 6],
    ]);
});

it('never includes data from another company', function () {
    // The other company has an identical scenario: totals would double if it leaked.
    expect(runReport($this, 'sales-by-period', ['group_by' => 'month'])['totals']['total'])->toBe(12140)
        ->and(runReport($this, 'receivables-aging')['totals']['total'])->toBe(11140);
});

it('rejects filter ids that belong to another company', function () {
    $foreignWarehouse = tenant()->run($this->other, fn () => Warehouse::first());

    $this->actingAs($this->accountant)
        ->get(route('reports.show', ['key' => 'sales-by-period', 'warehouse_id' => $foreignWarehouse->id]))
        ->assertSessionHasErrors('warehouse_id');
});

it('returns 404 for unknown reports and requires reports.view', function () {
    $this->actingAs($this->accountant)->get(route('reports.show', 'does-not-exist'))->assertNotFound();

    [$seller] = memberWithRole(SystemRole::Sales, $this->company);
    $this->actingAs($seller)->get(route('reports.show', 'sales-by-period'))->assertForbidden();
});

describe('exports', function () {
    it('generates a CSV in the queue with formula injection neutralised', function () {
        $this->actingAs($this->accountant)
            ->post(route('reports.export', 'sales-by-customer'), ['format' => 'csv'])
            ->assertSessionHasNoErrors();

        $export = tenant()->run($this->company, fn () => ReportExport::sole());
        expect($export->status)->toBe('completed')
            ->and($export->rows_count)->toBe(2);

        $csv = Storage::disk('local')->get($export->file_path);
        expect($csv)->toStartWith("\xEF\xBB\xBFCustomer,Orders")
            ->and($csv)->toContain('"\'=HYPERLINK(""http://evil.test"")"')
            ->and($csv)->not->toContain(',=HYPERLINK');
    });

    it('generates Excel and PDF files', function (string $format, string $signature) {
        $this->actingAs($this->accountant)->post(route('reports.export', 'profit'), ['format' => $format]);

        $export = tenant()->run($this->company, fn () => ReportExport::sole());
        expect($export->status)->toBe('completed')
            ->and(substr(Storage::disk('local')->get($export->file_path), 0, strlen($signature)))->toBe($signature);
    })->with([
        'xlsx (zip container)' => ['xlsx', 'PK'],
        'pdf' => ['pdf', '%PDF'],
    ]);

    it('lets only the requester download the export', function () {
        $this->actingAs($this->accountant)->post(route('reports.export', 'profit'), ['format' => 'csv']);
        $export = tenant()->run($this->company, fn () => ReportExport::sole());

        $this->actingAs($this->accountant)->get(route('reports.exports.download', $export))->assertOk();
        $this->actingAs($this->manager)->get(route('reports.exports.download', $export))->assertNotFound();

        [$outsider] = memberWithRole(SystemRole::Owner);
        $this->actingAs($outsider)->get(route('reports.exports.download', $export))->assertNotFound();
    });

    it('requires reports.export and a valid format', function () {
        $this->actingAs($this->accountant)->post(route('reports.export', 'profit'), ['format' => 'exe'])->assertSessionHasErrors('format');

        tenant()->run($this->company, fn () => App\Models\Role::firstWhere('slug', 'accountant')->syncPermissions([App\Enums\Permission::ReportsView]));
        app(App\Services\Authorization\PermissionResolver::class)->flush();

        $this->actingAs($this->accountant)->post(route('reports.export', 'profit'), ['format' => 'csv'])->assertForbidden();
    });
});
