<?php

namespace Database\Seeders;

use App\Actions\Catalog\SaveCategory;
use App\Actions\Catalog\SaveProduct;
use App\Actions\Companies\CreateCompany;
use App\Actions\Expenses\ReviewExpense;
use App\Actions\Expenses\SaveExpense;
use App\Actions\Inventory\AdjustStock;
use App\Actions\Inventory\TransferStock;
use App\Actions\Invoicing\CreateInvoiceFromSale;
use App\Actions\Invoicing\IssueInvoice;
use App\Actions\Invoicing\MarkOverdueInvoices;
use App\Actions\Invoicing\RegisterSupplierBill;
use App\Actions\Payments\RecordPayment;
use App\Actions\Purchasing\ChangePurchaseOrderStatus;
use App\Actions\Purchasing\ReceivePurchaseOrder;
use App\Actions\Purchasing\SavePurchaseOrder;
use App\Actions\Sales\CancelSale;
use App\Actions\Sales\ConfirmSale;
use App\Actions\Sales\SaveSale;
use App\Actions\Warehouses\SaveWarehouse;
use App\DTOs\CompanyData;
use App\DTOs\PaymentData;
use App\DTOs\ProductData;
use App\DTOs\PurchaseOrderData;
use App\DTOs\SaleData;
use App\Enums\PaymentMethod;
use App\Enums\ProductStatus;
use App\Enums\SystemRole;
use App\Models\Company;
use App\Models\Customer;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\Notification;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\Sale;
use App\Models\StockLevel;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * A realistic demo: "Demo Company" with four months of activity, plus a
 * small second company that shows multi-tenancy.
 *
 * Everything goes through the real Actions, executed in chronological order
 * from an agenda with the clock set to each simulated moment. Numbering,
 * stock ledger, audit trail, notifications and timestamps are therefore
 * exactly what real usage would have produced. Deterministic (fixed
 * pseudo-random seed) and idempotent (skips when the demo owner exists).
 *
 * Every demo user's password is "password".
 */
class DemoSeeder extends Seeder
{
    public const OWNER_EMAIL = 'owner@demo.test';

    /** Days of simulated activity (tests use a shorter history). */
    public int $historyDays = 120;

    /** Tax on every demo line, in basis points (8%). */
    private const TAX = 800;

    /** @var array<string, User> role => user */
    private array $users = [];

    /** @var list<Product> */
    private array $products = [];

    /** @var list<Customer> */
    private array $customers = [];

    /** @var array<string, Supplier> product code => supplier */
    private array $suppliers = [];

    /** @var list<array{at: CarbonInterface, seq: int, step: Closure(): mixed}> */
    private array $agenda = [];

    private int $sequence = 0;

    private Company $company;

    private Warehouse $main;

    private Warehouse $north;

    private Carbon $today;

    private int $seed = 20260930;

    public function run(): void
    {
        if (User::where('email', self::OWNER_EMAIL)->exists()) {
            $this->command->info('Demo data already present, skipping.');

            return;
        }

        $this->today = now()->startOfDay();
        $start = $this->today->copy()->subDays($this->historyDays);

        // Jobs (invoice PDFs, notifications) run inline so they carry the
        // simulated date instead of the moment a worker picks them up.
        $queue = config('queue.default');
        config(['queue.default' => 'sync']);

        try {
            $this->createUsers();
            $this->at($start->copy()->subDays(5), fn () => $this->createCompany());

            app(TenantContext::class)->run($this->company, function () use ($start): void {
                $this->at($start->copy()->subDays(5), function (): void {
                    $this->createCatalog();
                    $this->createParties();
                });

                $this->planHistory($start);
                $this->planOpenWork();
                $this->runAgenda(until: now());
            });

            $this->createSecondCompany();
        } finally {
            Date::setTestNow();
            Auth::forgetUser();
            config(['queue.default' => $queue]);
        }

        // Past-due invoices, in the company's time zone.
        app(TenantContext::class)->run($this->company, fn () => app(MarkOverdueInvoices::class)->handle());

        // Users have read what is older than a few days; the bell shows the rest.
        Notification::query()
            ->whereNull('read_at')
            ->where('created_at', '<', $this->daysAgo(3))
            ->update(['read_at' => DB::raw('created_at')]);

        $this->command->info('Demo ready: '.self::OWNER_EMAIL.' / password  (also admin@, manager@, accountant@, sales@, warehouse@, employee@demo.test)');
    }

    // ------------------------------------------------------------------
    // Setup
    // ------------------------------------------------------------------

    private function createUsers(): void
    {
        $people = [
            SystemRole::Owner->value => ['Sofía Ramírez', 'owner'],
            SystemRole::Administrator->value => ['Daniel Ortega', 'admin'],
            SystemRole::Manager->value => ['Laura Méndez', 'manager'],
            SystemRole::Accountant->value => ['Carlos Rivas', 'accountant'],
            SystemRole::Sales->value => ['Valentina Cruz', 'sales'],
            SystemRole::Warehouse->value => ['Andrés Molina', 'warehouse'],
            SystemRole::Employee->value => ['Camila Torres', 'employee'],
        ];

        foreach ($people as $role => [$name, $mailbox]) {
            $user = new User(['name' => $name, 'email' => "{$mailbox}@demo.test", 'password' => 'password']);
            $user->forceFill(['email_verified_at' => now()])->save();
            $this->users[$role] = $user;
        }
    }

    private function createCompany(): void
    {
        $this->company = app(CreateCompany::class)->handle($this->user(SystemRole::Owner), new CompanyData(
            name: 'Demo Company',
            country: 'US',
            currency: 'USD',
            timezone: 'America/New_York',
            legalName: 'Demo Company LLC',
            taxId: '84-1234567',
            email: 'hello@demo.test',
            phone: '+1 212 555 0100',
            address: '350 Fifth Avenue',
            city: 'New York',
        ));

        $roles = app(TenantContext::class)->run($this->company, fn () => Role::query()->get()->keyBy('slug'));

        foreach ($this->users as $role => $user) {
            if ($role !== SystemRole::Owner->value) {
                $this->company->users()->attach($user->id, ['status' => 'active', 'joined_at' => now()]);
                $user->membershipIn($this->company)?->syncRoles([$roles[$role]]);
            }
        }
    }

    private function createCatalog(): void
    {
        $this->as(SystemRole::Manager);

        $this->main = Warehouse::where('is_default', true)->sole();
        $this->north = app(SaveWarehouse::class)->handle(null, ['code' => 'NORTH', 'name' => 'North distribution center', 'city' => 'Boston']);

        foreach (DemoCatalog::products() as $code => [$categoryName, $items]) {
            $category = app(SaveCategory::class)->handle(null, $categoryName, null, null);

            foreach ($items as $i => [$name, $cost, $price, $minStock]) {
                $this->products[] = app(SaveProduct::class)->handle(null, new ProductData(
                    name: $name,
                    sku: sprintf('%s-%03d', $code, $i + 1),
                    cost: $cost,
                    price: $price,
                    taxRate: self::TAX,
                    minStock: $minStock,
                    status: ProductStatus::Active,
                    barcode: sprintf('0%012d', crc32($name)),
                    categoryId: $category->id,
                ));
            }
        }
    }

    private function createParties(): void
    {
        foreach (DemoCatalog::customers() as [$name, $city, $email]) {
            $this->customers[] = Customer::create([
                'kind' => 'company', 'name' => $name, 'city' => $city, 'country' => 'US', 'email' => $email,
                'tax_id' => sprintf('%02d-%07d', $this->random(10, 99), $this->random(1_000_000, 9_999_999)),
            ]);
        }

        foreach (DemoCatalog::suppliers() as $code => [$name, $contact, $email]) {
            $this->suppliers[$code] = Supplier::create([
                'kind' => 'company', 'name' => $name, 'contact_name' => $contact, 'email' => $email, 'country' => 'US',
            ]);
        }
    }

    // ------------------------------------------------------------------
    // Timeline
    // ------------------------------------------------------------------

    private function planHistory(Carbon $start): void
    {
        // Opening stock: one purchase order per supplier, received and billed.
        $this->schedule($start->copy()->setTime(8, 0), fn () => $this->restock($this->products, fn (Product $p) => $p->min_stock * $this->random(5, 9)));

        $categories = collect(['Rent', 'Utilities', 'Marketing', 'Software subscriptions', 'Travel'])
            ->mapWithKeys(fn (string $name) => [$name => $this->at($start, fn () => ExpenseCategory::create(['name' => $name]))]);

        $monthsBilled = [];

        for ($day = $start->copy()->addDays(3); $day->lt($this->today); $day->addDay()) {
            if ($day->isWeekend()) {
                continue;
            }

            for ($n = $this->random(0, 3); $n > 0; $n--) {
                $this->schedule($day->copy()->setTime($this->random(9, 17), $this->random(0, 59)), fn () => $this->sell());
            }

            if (! isset($monthsBilled[$day->format('Y-m')])) {
                $monthsBilled[$day->format('Y-m')] = true;
                $when = $day->copy()->setTime(9, 0);
                $this->schedule($when, function () use ($categories, $when): void {
                    $this->expense($categories['Rent'], 'Office rent '.$when->format('F Y'), 450000);
                    $this->expense($categories['Utilities'], 'Electricity and internet', $this->random(38000, 52000));
                    $this->expense($categories['Software subscriptions'], 'SaaS tools', 89900);
                });
            }

            // Every other Monday the manager reorders whatever runs low.
            if ($day->isMonday() && $day->weekOfYear % 2 === 0) {
                $this->schedule($day->copy()->setTime(8, 30), fn () => $this->restockLow());
            }
        }

        $this->schedule($this->daysAgo(40)->setTime(12, 0), function () use ($categories): void {
            $this->expense($categories['Marketing'], 'Trade fair booth', 320000);
            $this->expense($categories['Travel'], 'Client visit, Chicago', 76550);
        });

        $this->schedule($this->daysAgo(55)->setTime(11, 0), function (): void {
            $this->as(SystemRole::Warehouse);
            foreach (array_slice($this->products, 10, 6) as $product) {
                if ($this->onHand($product, $this->main) > 10) {
                    app(TransferStock::class)->handle($product, $this->main, $this->north, 4, 'Stock for the Boston office');
                }
            }
        });

        $this->schedule($this->daysAgo(21)->setTime(16, 0), function (): void {
            $this->as(SystemRole::Warehouse);
            $product = collect($this->products)->first(fn (Product $p) => $this->onHand($p, $this->main) >= 2);
            if ($product !== null) {
                app(AdjustStock::class)->handle($product, $this->main, $this->onHand($product, $this->main) - 1, 'Cycle count: one unit damaged');
            }
        });
    }

    /**
     * Work in progress: things waiting for someone to act today.
     */
    private function planOpenWork(): void
    {
        $this->schedule($this->daysAgo(12)->setTime(10, 0), function (): void {
            $sale = $this->sell(invoice: false);
            if ($sale !== null) {
                $this->as(SystemRole::Manager);
                app(CancelSale::class)->handle($sale, $this->user(SystemRole::Manager), 'Customer postponed the project');
            }
        });

        // Approved and partially received / approved and expected / awaiting approval.
        $this->schedule($this->daysAgo(6)->setTime(10, 0), function (): void {
            $order = $this->purchaseOrder('ELE', array_slice($this->products, 10, 3), 12, approve: true);
            $this->as(SystemRole::Warehouse);
            app(ReceivePurchaseOrder::class)->handle($order, [$order->items()->orderBy('id')->value('id') => 6], $this->user(SystemRole::Warehouse));
        });
        $this->schedule($this->daysAgo(3)->setTime(11, 0), fn () => $this->purchaseOrder('NET', array_slice($this->products, 20, 4), 10, approve: true));
        $this->schedule($this->today->copy()->subDay()->setTime(15, 30), fn () => $this->purchaseOrder('FUR', array_slice($this->products, 0, 2), 8, approve: false));

        $this->schedule(now()->subMinutes(50), function (): void {
            // Left pending: shows up in the approval queue.
            $this->as(SystemRole::Manager);
            app(SaveExpense::class)->handle(null, [
                'category_id' => ExpenseCategory::firstWhere('name', 'Travel')->id,
                'description' => 'Taxi to supplier meeting',
                'amount' => 4250,
                'expense_date' => now()->toDateString(),
                'payment_method' => 'card',
            ], null, $this->user(SystemRole::Manager));
        }, wallClock: false);

        $this->schedule(now()->subMinutes(20), function (): void {
            $this->as(SystemRole::Sales);
            for ($i = 0; $i < 3; $i++) {
                $product = $this->pick($this->products);
                app(SaveSale::class)->handle(null, new SaleData($this->pick($this->customers)->id, $this->main->id, now()->toDateString(), 'Quote requested by phone', [
                    ['product_id' => $product->id, 'quantity' => $this->random(2, 6), 'unit_price' => $product->price, 'discount_rate' => 0, 'tax_rate' => self::TAX],
                ]), $this->user(SystemRole::Sales));
            }
        }, wallClock: false);
    }

    // ------------------------------------------------------------------
    // Business steps
    // ------------------------------------------------------------------

    /**
     * A confirmed sale of whatever is in stock; invoiced the next morning
     * and collected later (most on time, some in part, some never).
     */
    private function sell(bool $invoice = true): ?Sale
    {
        $role = $this->random(1, 4) === 1 ? SystemRole::Manager : SystemRole::Sales;
        $this->as($role);
        $warehouse = $this->random(1, 6) === 1 ? $this->north : $this->main;

        $lines = [];
        foreach ($this->pickMany($this->products, $this->random(1, 4)) as $product) {
            $quantity = min($this->random(1, 4), $this->onHand($product, $warehouse));
            if ($quantity > 0) {
                $lines[] = [
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $product->price,
                    'discount_rate' => $this->random(1, 5) === 1 ? 500 : 0,
                    'tax_rate' => self::TAX,
                ];
            }
        }

        if ($lines === []) {
            return null;
        }

        $user = $this->user($role);
        $sale = app(ConfirmSale::class)->handle(
            app(SaveSale::class)->handle(null, new SaleData($this->pick($this->customers)->id, $warehouse->id, now()->toDateString(), null, $lines), $user),
            $user,
        );

        if ($invoice) {
            $this->schedule(now()->addDay()->setTime(9, 30), fn () => $this->invoiceAndCollect($sale));
        }

        return $sale;
    }

    private function invoiceAndCollect(Sale $sale): void
    {
        $this->as(SystemRole::Accountant);
        $accountant = $this->user(SystemRole::Accountant);

        $invoice = app(IssueInvoice::class)->handle(
            app(CreateInvoiceFromSale::class)->handle($sale, now()->addDays(30)->toDateString(), null, $accountant),
            $accountant,
        );

        $behaviour = $this->random(1, 10);
        $paidOn = now()->addDays($this->random(3, 28))->setTime(14, 0);

        if ($behaviour <= 8) {
            $this->schedule($paidOn, fn () => $this->collect($invoice, $invoice->total));
        } elseif ($behaviour === 9) {
            $this->schedule($paidOn, fn () => $this->collect($invoice, intdiv($invoice->total, 2)));
        }
        // 10: never paid, overdue once the due date passes.
    }

    private function collect(Invoice|SupplierBill $document, int $amount): void
    {
        $this->as(SystemRole::Accountant);
        $method = $document instanceof SupplierBill
            ? PaymentMethod::BankTransfer
            : $this->pick([PaymentMethod::BankTransfer, PaymentMethod::Card, PaymentMethod::BankTransfer, PaymentMethod::Cash]);

        app(RecordPayment::class)->handle(
            $document->fresh() ?? $document,
            new PaymentData($amount, $method, now()->toDateString(), ($document instanceof SupplierBill ? 'WIRE-' : 'TRX-').$this->random(100000, 999999)),
            $this->user(SystemRole::Accountant),
        );
    }

    /**
     * One purchase order per supplier; received two days later, billed the
     * day after, and the bill paid within terms.
     *
     * @param  list<Product>  $products
     * @param  Closure(Product): int  $quantity
     */
    private function restock(array $products, Closure $quantity): void
    {
        foreach (collect($products)->groupBy(fn (Product $p) => explode('-', $p->sku)[0]) as $code => $group) {
            $order = $this->purchaseOrder((string) $code, $group->all(), $quantity, approve: true);

            $this->schedule(now()->addDays(2)->setTime(10, 0), function () use ($order): void {
                $this->as(SystemRole::Warehouse);
                app(ReceivePurchaseOrder::class)->handle($order, $order->items()->pluck('quantity', 'id')->all(), $this->user(SystemRole::Warehouse));

                $this->schedule(now()->addDay()->setTime(11, 0), function () use ($order): void {
                    $this->as(SystemRole::Accountant);
                    $bill = app(RegisterSupplierBill::class)->handle(
                        $order, 'SUP-'.$this->random(10000, 99999), now()->toDateString(), now()->addDays(30)->toDateString(),
                        $order->items()->pluck('quantity', 'id')->all(), $this->user(SystemRole::Accountant),
                    );

                    $this->schedule(now()->addDays($this->random(15, 28))->setTime(15, 0), fn () => $this->collect($bill, $bill->total));
                });
            });
        }
    }

    private function restockLow(): void
    {
        $low = array_values(array_filter(
            $this->products,
            fn (Product $product) => $this->onHand($product) < $product->min_stock * 2,
        ));

        if ($low !== []) {
            $this->restock($low, fn (Product $p) => $p->min_stock * 4);
        }
    }

    /**
     * @param  list<Product>  $products
     * @param  int|Closure(Product): int  $quantity
     */
    private function purchaseOrder(string $supplierCode, array $products, int|Closure $quantity, bool $approve): PurchaseOrder
    {
        $this->as(SystemRole::Manager);

        $order = app(SavePurchaseOrder::class)->handle(null, new PurchaseOrderData(
            $this->suppliers[$supplierCode]->id,
            $this->main->id,
            now()->toDateString(),
            now()->addDays(7)->toDateString(),
            null,
            array_map(fn (Product $p) => [
                'product_id' => $p->id,
                'quantity' => is_int($quantity) ? $quantity : $quantity($p),
                'unit_cost' => $p->cost,
                'tax_rate' => self::TAX,
            ], $products),
        ), $this->user(SystemRole::Manager));

        app(ChangePurchaseOrderStatus::class)->submit($order);

        if ($approve) {
            $this->as(SystemRole::Owner);
            app(ChangePurchaseOrderStatus::class)->approve($order, $this->user(SystemRole::Owner));
        }

        return $order;
    }

    private function expense(ExpenseCategory $category, string $description, int $amount): void
    {
        $this->as(SystemRole::Accountant);
        $expense = app(SaveExpense::class)->handle(null, [
            'category_id' => $category->id,
            'description' => $description,
            'amount' => $amount,
            'expense_date' => now()->toDateString(),
            'payment_method' => 'bank_transfer',
        ], null, $this->user(SystemRole::Accountant));

        $this->as(SystemRole::Owner);
        app(ReviewExpense::class)->approve($expense, $this->user(SystemRole::Owner));
    }

    /**
     * A small second company owned by the same person: the company switcher
     * and tenant isolation in action.
     */
    private function createSecondCompany(): void
    {
        $this->at($this->today->copy()->subDays(30)->setTime(10, 0), function (): void {
            $owner = $this->user(SystemRole::Owner);
            Auth::setUser($owner);

            $company = app(CreateCompany::class)->handle($owner, new CompanyData(
                name: 'Andes Retail', country: 'CO', currency: 'COP', timezone: 'America/Bogota', city: 'Bogotá',
            ));

            app(TenantContext::class)->run($company, function () use ($owner): void {
                $warehouse = Warehouse::where('is_default', true)->sole();
                $supplier = Supplier::create(['kind' => 'company', 'name' => 'Distribuidora Andina', 'country' => 'CO']);
                $customer = Customer::create(['kind' => 'company', 'name' => 'Café Montaña', 'city' => 'Medellín', 'country' => 'CO']);

                $products = [];
                foreach ([['Silla ergonómica', 180000, 390000], ['Escritorio en L', 420000, 890000], ['Lámpara LED', 45000, 99000]] as $i => [$name, $cost, $price]) {
                    $products[] = app(SaveProduct::class)->handle(null, new ProductData(
                        name: $name, sku: sprintf('AR-%03d', $i + 1), cost: $cost, price: $price, taxRate: 1900, minStock: 3, status: ProductStatus::Active,
                    ));
                }

                $order = app(SavePurchaseOrder::class)->handle(null, new PurchaseOrderData(
                    $supplier->id, $warehouse->id, now()->toDateString(), null, null,
                    array_map(fn (Product $p) => ['product_id' => $p->id, 'quantity' => 20, 'unit_cost' => $p->cost, 'tax_rate' => 1900], $products),
                ), $owner);
                app(ChangePurchaseOrderStatus::class)->submit($order);
                app(ChangePurchaseOrderStatus::class)->approve($order, $owner);
                app(ReceivePurchaseOrder::class)->handle($order, $order->items()->pluck('quantity', 'id')->all(), $owner);

                app(ConfirmSale::class)->handle(app(SaveSale::class)->handle(null, new SaleData($customer->id, $warehouse->id, now()->toDateString(), null, [
                    ['product_id' => $products[0]->id, 'quantity' => 4, 'unit_price' => $products[0]->price, 'discount_rate' => 0, 'tax_rate' => 1900],
                ]), $owner), $owner);
            });
        });
    }

    // ------------------------------------------------------------------
    // Agenda and helpers
    // ------------------------------------------------------------------

    /**
     * Times given to the agenda are wall-clock times at the company (a sale
     * "at 10:15" happens at 10:15 in New York), stored in UTC like the app.
     * Pass $wallClock = false for moments that already are absolute.
     *
     * @param  Closure(): mixed  $step
     */
    private function schedule(CarbonInterface $at, Closure $step, bool $wallClock = true): void
    {
        $moment = $wallClock ? Carbon::parse($at->format('Y-m-d H:i:s'), $this->company->timezone)->utc() : $at->copy();

        $this->agenda[] = ['at' => $moment, 'seq' => $this->sequence++, 'step' => $step];
    }

    /**
     * Runs scheduled steps in chronological order (steps may schedule more).
     * Steps due after $until never happen: they are in the future.
     */
    private function runAgenda(CarbonInterface $until): void
    {
        while (true) {
            usort($this->agenda, fn (array $a, array $b) => [$a['at'], $a['seq']] <=> [$b['at'], $b['seq']]);
            $next = $this->agenda[0] ?? null;

            if ($next === null || $next['at']->gt($until)) {
                return;
            }

            array_shift($this->agenda);
            $this->at($next['at'], $next['step']);
        }
    }

    /**
     * Run a step with the clock set to a simulated moment, restoring the
     * previous clock afterwards (steps can nest).
     *
     * @template T
     *
     * @param  Closure(): T  $step
     * @return T
     */
    private function at(CarbonInterface $moment, Closure $step): mixed
    {
        $previous = Date::hasTestNow() ? Date::now() : null;
        Date::setTestNow($moment);

        try {
            return $step();
        } finally {
            Date::setTestNow($previous);
        }
    }

    /**
     * A moment inside the simulated history, however short it is.
     */
    private function daysAgo(int $days): Carbon
    {
        return $this->today->copy()->subDays(min($days, $this->historyDays - 10));
    }

    private function onHand(Product $product, ?Warehouse $warehouse = null): int
    {
        return (int) StockLevel::query()
            ->where('product_id', $product->id)
            ->when($warehouse, fn ($q) => $q->where('warehouse_id', $warehouse->id))
            ->sum('quantity');
    }

    private function as(SystemRole $role): void
    {
        Auth::setUser($this->user($role));
    }

    private function user(SystemRole $role): User
    {
        return $this->users[$role->value];
    }

    /**
     * @template T
     *
     * @param  list<T>  $items
     * @return T
     */
    private function pick(array $items): mixed
    {
        return $items[$this->random(0, count($items) - 1)];
    }

    /**
     * @template T
     *
     * @param  list<T>  $items
     * @return list<T>
     */
    private function pickMany(array $items, int $count): array
    {
        $picked = [];
        while (count($picked) < min($count, count($items))) {
            $picked[$this->random(0, count($items) - 1)] = true;
        }

        return array_values(array_intersect_key($items, $picked));
    }

    /**
     * Deterministic pseudo-random integer: the same demo on every install.
     */
    private function random(int $min, int $max): int
    {
        $this->seed = ($this->seed * 1103515245 + 12345) % 2147483648;

        return $min + $this->seed % ($max - $min + 1);
    }
}
