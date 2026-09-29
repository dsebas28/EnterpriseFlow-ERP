<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
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

    private function configurePasswords(): void
    {
        Password::defaults(fn () => $this->app->isProduction()
            ? Password::min(12)->mixedCase()->numbers()->symbols()->uncompromised()
            : Password::min(8));
    }
}
