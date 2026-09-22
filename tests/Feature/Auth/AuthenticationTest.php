<?php

use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create([
        'email' => 'ana@example.test',
        'password' => 'senha-bem-segura',
    ]);
});

it('shows the login form to a visitor', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('name="_token"', false);
});

it('signs in with valid credentials and regenerates the session', function () {
    $this->get(route('login'));
    $sessionBefore = session()->getId();

    $this->post(route('login'), [
        'email' => 'ana@example.test',
        'password' => 'senha-bem-segura',
    ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($this->user);
    expect(session()->getId())->not->toBe($sessionBefore);
});

it('refuses wrong credentials with a message that does not reveal the account', function () {
    $this->post(route('login'), [
        'email' => 'ana@example.test',
        'password' => 'senha-errada-qualquer',
    ])->assertSessionHasErrors(['email' => 'E-mail ou senha incorretos.']);

    $this->assertGuest();
});

it('signs out and invalidates the session', function () {
    $this->actingAs($this->user);
    $sessionBefore = session()->getId();

    $this->post(route('logout'))->assertRedirect(route('login'));

    $this->assertGuest();
    expect(session()->getId())->not->toBe($sessionBefore);
});
