<?php

use App\Enums\SystemRole;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockLevel;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Queue;

function runDemoSeeder(): void
{
    // Through db:seed, like the Docker entrypoint; with a shorter history
    // (the full demo simulates 120 days).
    $seeder = new DemoSeeder;
    $seeder->historyDays = 30;
    app()->instance(DemoSeeder::class, $seeder);

    test()->artisan('db:seed', ['--class' => DemoSeeder::class, '--force' => true])->assertSuccessful();
}

beforeEach(function () {
    Queue::fake(); // PDFs and notifications are covered elsewhere
    $this->travelTo('2026-09-30 12:00:00');
    runDemoSeeder();

    $this->owner = User::firstWhere('email', DemoSeeder::OWNER_EMAIL);
    $this->demo = Company::firstWhere('name', 'Demo Company');
});

it('creates a user per role, all able to sign in', function () {
    $hasRole = fn (string $email, SystemRole $role) => tenant()->run(
        $this->demo,
        fn () => User::firstWhere('email', $email)->membershipIn($this->demo)->hasRole($role),
    );

    expect(User::where('email', 'like', '%@demo.test')->count())->toBe(7)
        ->and($hasRole(DemoSeeder::OWNER_EMAIL, SystemRole::Owner))->toBeTrue()
        ->and($hasRole('sales@demo.test', SystemRole::Sales))->toBeTrue()
        ->and($hasRole('warehouse@demo.test', SystemRole::Warehouse))->toBeTrue();

    $this->post('/login', ['email' => 'accountant@demo.test', 'password' => 'password'])
        ->assertRedirect(route('dashboard', absolute: false));
});

it('builds the demo catalogue and business history through the real actions', function () {
    tenant()->run($this->demo, function () {
        expect(Product::count())->toBe(50)
            ->and(Customer::count())->toBe(20)
            ->and(Supplier::count())->toBe(10)
            ->and(Sale::count())->toBeGreaterThan(20)
            ->and(Invoice::count())->toBeGreaterThan(10)
            ->and(Payment::count())->toBeGreaterThan(5)
            ->and(StockLevel::where('quantity', '<', 0)->count())->toBe(0);

        // Chronological simulation: document numbers follow document dates.
        $dates = Sale::orderBy('number')->pluck('sale_date')->map->toDateString()->all();
        $sorted = $dates;
        sort($sorted);
        expect($dates)->toBe($sorted);

        // Work waiting for someone today.
        expect(Sale::where('status', 'draft')->count())->toBe(3)
            ->and(Sale::where('status', 'cancelled')->count())->toBe(1);
    });
});

it('adds a second company for the same owner, isolated from the first', function () {
    $andes = Company::firstWhere('name', 'Andes Retail');

    expect($andes)->not->toBeNull()
        ->and($this->owner->activeCompanies()->pluck('name')->sort()->values()->all())->toBe(['Andes Retail', 'Demo Company'])
        ->and(tenant()->run($andes, fn () => Product::count()))->toBe(3);
});

it('is idempotent', function () {
    $before = [User::count(), Company::count(), tenant()->run($this->demo, fn () => Sale::count())];

    runDemoSeeder();

    expect([User::count(), Company::count(), tenant()->run($this->demo, fn () => Sale::count())])->toBe($before);
});
