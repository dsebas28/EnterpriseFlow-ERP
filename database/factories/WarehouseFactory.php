<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Warehouse;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Warehouse>
 */
class WarehouseFactory extends Factory
{
    public function definition(): array
    {
        $city = fake()->city();

        return [
            'company_id' => fn () => app(TenantContext::class)->id() ?? Company::factory(),
            'code' => strtoupper(fake()->unique()->bothify('WH-###')),
            'name' => "{$city} warehouse",
            'address' => fake()->streetAddress(),
            'city' => $city,
        ];
    }

    public function default(): static
    {
        return $this->afterMaking(fn (Warehouse $warehouse) => $warehouse->is_default = true);
    }
}
