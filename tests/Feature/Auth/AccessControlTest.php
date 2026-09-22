<?php

use App\Models\User;

it('sends a visitor from the dashboard to the login page', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

it('lets an authenticated user reach the dashboard', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk();
});

it('keeps an authenticated user away from the guest pages', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('login'))->assertRedirect(route('dashboard'));
    $this->get(route('register'))->assertRedirect(route('dashboard'));
});
