<?php

namespace App\Providers;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReceipt;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Listeners in app/Listeners are auto-discovered by Laravel; do not
        // register them here as well or every event is handled twice.
        $this->configureModels();
        $this->configureMorphMap();
        $this->configurePasswords();

        // Refuse migrate:fresh / db:wipe and friends against production.
        DB::prohibitDestructiveCommands($this->app->isProduction());
    }

    private function configureModels(): void
    {
        // Outside production, surface N+1 queries, typos in attribute names
        // and silently discarded mass-assignment instead of hiding them.
        Model::shouldBeStrict(! $this->app->isProduction());
    }

    /**
     * Polymorphic columns store these stable aliases instead of PHP class
     * names, so renaming or moving a class never breaks stored references.
     * Enforced: morphing an unmapped model throws instead of leaking a class name.
     */
    private function configureMorphMap(): void
    {
        Relation::enforceMorphMap([
            'user' => User::class,
            'company' => Company::class,
            'product' => Product::class,
            'warehouse' => Warehouse::class,
            'supplier' => Supplier::class,
            'purchase_order' => PurchaseOrder::class,
            'purchase_receipt' => PurchaseReceipt::class,
            'customer' => Customer::class,
            'sale' => Sale::class,
            'invoice' => Invoice::class,
        ]);
    }

    private function configurePasswords(): void
    {
        Password::defaults(fn () => $this->app->isProduction()
            ? Password::min(12)->mixedCase()->numbers()->symbols()->uncompromised()
            : Password::min(8));
    }
}
