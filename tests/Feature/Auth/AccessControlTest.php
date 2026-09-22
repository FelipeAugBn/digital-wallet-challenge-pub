<?php

use App\Models\User;
use App\Models\Wallet;

test('manda o visitante do painel para a tela de login', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('permite que quem está autenticado abra o painel', function () {
    // A carteira entra junto porque o cadastro sempre cria as duas coisas na
    // mesma transação: pessoa autenticada sem carteira não existe em produção.
    $user = User::factory()->create();
    Wallet::create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();
});

test('mantém quem está autenticado longe das telas de visitante', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('login'))->assertRedirect(route('dashboard'));
    $this->get(route('register'))->assertRedirect(route('dashboard'));
});
