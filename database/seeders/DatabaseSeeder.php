<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * `php artisan db:seed` loads the demo (idempotent).
     */
    public function run(): void
    {
        $this->call(DemoSeeder::class);
    }
}
