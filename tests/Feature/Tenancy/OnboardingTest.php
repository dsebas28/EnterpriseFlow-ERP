<?php

use App\Enums\MembershipStatus;
use App\Http\Middleware\ResolveCurrentCompany;
use App\Models\Company;
use App\Models\User;

it('renders the onboarding screen', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('onboarding.company.create'))
        ->assertOk();
});

it('creates a company, makes the user a member and activates it', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('onboarding.company.store'), [
            'name' => 'Acme Distribution',
            'tax_id' => '900123456-7',
            'country' => 'co',
            'currency' => 'cop',
            'timezone' => 'America/Bogota',
        ])
        ->assertRedirect(route('dashboard'));

    $company = Company::sole();

    expect($company->name)->toBe('Acme Distribution')
        ->and($company->country)->toBe('CO')
        ->and($company->currency)->toBe('COP')
        ->and($user->companies()->sole()->membership->status)->toBe(MembershipStatus::Active);

    $this->assertEquals($company->id, session(ResolveCurrentCompany::SESSION_KEY));
});

it('validates company input', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('onboarding.company.store'), [
            'name' => '',
            'country' => 'Colombia',
            'currency' => 'PESOS',
            'timezone' => 'Mars/Olympus',
        ])
        ->assertSessionHasErrors(['name', 'country', 'currency', 'timezone']);
});

it('rejects a tax id already registered in the same country', function () {
    Company::factory()->create(['country' => 'CO', 'tax_id' => '900123456-7']);

    $this->actingAs(User::factory()->create())
        ->post(route('onboarding.company.store'), [
            'name' => 'Clone',
            'tax_id' => '900123456-7',
            'country' => 'CO',
            'currency' => 'COP',
            'timezone' => 'America/Bogota',
        ])
        ->assertSessionHasErrors('tax_id');
});

it('requires authentication', function () {
    $this->get(route('onboarding.company.create'))->assertRedirect(route('login'));
});
