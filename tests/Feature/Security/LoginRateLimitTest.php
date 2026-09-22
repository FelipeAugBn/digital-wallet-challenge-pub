<?php

use App\Models\User;

require_once __DIR__.'/helpers.php';

beforeEach(function () {
    $this->user = User::factory()->create([
        'email' => 'ana@example.test',
        'password' => 'senha-bem-segura',
    ]);
});

test('aceita cinco tentativas de login por minuto e recusa a sexta', function () {
    foreach (range(1, 5) as $tentativa) {
        tentativaDeLogin('ana@example.test')->assertSessionHasErrors(['email']);
    }

    tentativaDeLogin('ana@example.test')->assertSessionHasErrors(['limite']);

    $this->assertGuest();
});

test('recusa a sexta tentativa mesmo quando a senha está certa', function () {
    foreach (range(1, 5) as $tentativa) {
        tentativaDeLogin('ana@example.test');
    }

    tentativaDeLogin('ana@example.test', 'senha-bem-segura')
        ->assertSessionHasErrors(['limite']);

    $this->assertGuest();
});

test('conta o mesmo e-mail escrito de outro jeito dentro da mesma cota', function () {
    tentativaDeLogin('ANA@EXAMPLE.TEST');
    tentativaDeLogin('  ana@example.test  ');
    tentativaDeLogin('Ana@Example.Test');
    tentativaDeLogin('ana@example.test');
    tentativaDeLogin('ANA@example.TEST');

    tentativaDeLogin('ana@example.test')->assertSessionHasErrors(['limite']);
});

test('dá cota própria a outro e-mail vindo do mesmo IP', function () {
    User::factory()->create(['email' => 'bia@example.test']);

    foreach (range(1, 5) as $tentativa) {
        tentativaDeLogin('ana@example.test');
    }

    tentativaDeLogin('bia@example.test')
        ->assertSessionHasErrors(['email' => 'E-mail ou senha incorretos.'])
        ->assertSessionDoesntHaveErrors(['limite']);
});

test('dá cota própria ao mesmo e-mail vindo de outro IP', function () {
    foreach (range(1, 5) as $tentativa) {
        tentativaDeLogin('ana@example.test');
    }

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10']);

    tentativaDeLogin('ana@example.test')
        ->assertSessionHasErrors(['email' => 'E-mail ou senha incorretos.'])
        ->assertSessionDoesntHaveErrors(['limite']);
});

test('informa quanto tempo falta para tentar de novo', function () {
    foreach (range(1, 5) as $tentativa) {
        tentativaDeLogin('ana@example.test');
    }

    $resposta = tentativaDeLogin('ana@example.test');

    expect((int) $resposta->headers->get('Retry-After'))->toBeGreaterThan(0)
        ->and(session('errors')->first('limite'))->toContain('Tente novamente em');
});
