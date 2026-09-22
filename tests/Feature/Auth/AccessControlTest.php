<?php

use App\Models\User;

test('manda o visitante do painel para a tela de login', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('permite que quem está autenticado abra o painel', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk();
});

test('mantém quem está autenticado longe das telas de visitante', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('login'))->assertRedirect(route('dashboard'));
    $this->get(route('register'))->assertRedirect(route('dashboard'));
});
