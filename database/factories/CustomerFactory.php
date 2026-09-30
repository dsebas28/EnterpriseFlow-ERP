<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Customer;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    public function definition(): array
    {
        $isCompany = fake()->boolean(60);

        return [
            'company_id' => fn () => app(TenantContext::class)->id() ?? Company::factory(),
            'kind' => $isCompany ? 'company' : 'person',
            'name' => $isCompany ? fake()->company() : fake()->name(),
            'tax_id' => fake()->unique()->numerify($isCompany ? '8########-#' : '1#########'),
            'email' => fake()->safeEmail(),
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
