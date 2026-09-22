<?php

use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create([
        'email' => 'ana@example.test',
        'password' => 'senha-bem-segura',
    ]);
});

test('mostra o formulário de login para o visitante', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('name="_token"', false);
});

test('entra com credenciais válidas e renova a sessão', function () {
    $this->get(route('login'));
    $sessionBefore = session()->getId();

    $this->post(route('login'), [
        'email' => 'ana@example.test',
        'password' => 'senha-bem-segura',
    ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($this->user);
    expect(session()->getId())->not->toBe($sessionBefore);
});

test('recusa credenciais erradas sem revelar se a conta existe', function () {
    $this->post(route('login'), [
        'email' => 'ana@example.test',
        'password' => 'senha-errada-qualquer',
    ])->assertSessionHasErrors(['email' => 'E-mail ou senha incorretos.']);

    $this->assertGuest();
});

test('sai da conta e invalida a sessão', function () {
    $this->actingAs($this->user);
    $sessionBefore = session()->getId();

    $this->post(route('logout'))->assertRedirect(route('login'));

    $this->assertGuest();
    expect(session()->getId())->not->toBe($sessionBefore);
});
