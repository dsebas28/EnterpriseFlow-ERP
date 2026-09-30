<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Supplier;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => fn () => app(TenantContext::class)->id() ?? Company::factory(),
            'kind' => 'company',
            'name' => fake()->company(),
            'tax_id' => fake()->unique()->numerify('9########-#'),
            'contact_name' => fake()->name(),
            'email' => fake()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'country' => 'CO',
            'status' => 'active',
        ];
    }

    public function inactive(): static
    {
        return $this->state(['status' => 'inactive']);
    }
}
