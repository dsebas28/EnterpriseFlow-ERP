<?php

namespace Database\Factories;

use App\Enums\CompanyStatus;
use App\Enums\MembershipStatus;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'legal_name' => fake()->company().' S.A.S.',
            'tax_id' => fake()->unique()->numerify('#########-#'),
            'email' => fake()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'country' => 'CO',
            'currency' => 'COP',
            'timezone' => 'America/Bogota',
        ];
    }

    public function suspended(): static
    {
        return $this->afterMaking(fn (Company $company) => $company->status = CompanyStatus::Suspended);
    }

    /**
     * Attach the given user as an active member.
     */
    public function withMember(User $user, MembershipStatus $status = MembershipStatus::Active): static
    {
        return $this->afterCreating(fn (Company $company) => $company->users()->attach($user->getKey(), [
            'status' => $status->value,
            'joined_at' => now(),
        ]));
    }
}
