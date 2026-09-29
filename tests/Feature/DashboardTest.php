<?php

use App\Models\User;

test('guests are redirected to the login page', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});

test('company members can visit the dashboard', function () {
    [$user] = companyWithMember();

    $this->actingAs($user)->get('/dashboard')->assertOk();
});

test('users without a company are sent to onboarding', function () {
    $this->actingAs(User::factory()->create())
        ->get('/dashboard')
        ->assertRedirect(route('onboarding.company.create'));
});
