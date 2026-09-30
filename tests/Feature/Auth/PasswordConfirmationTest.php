<?php

use App\Models\User;

it('renders the confirm password page', function () {
    $this->actingAs(User::factory()->create())
        ->get('/confirm-password')
        ->assertOk();
});

it('confirms the password', function () {
    $this->actingAs(User::factory()->create())
        ->post('/confirm-password', ['password' => 'password'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();
});

it('does not confirm a wrong password', function () {
    $this->actingAs(User::factory()->create())
        ->post('/confirm-password', ['password' => 'wrong-password'])
        ->assertSessionHasErrors();
});
