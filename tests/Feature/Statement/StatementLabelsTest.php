<?php

use Illuminate\Support\Facades\DB;

require_once __DIR__.'/helpers.php';

test('rotula o depósito na própria carteira', function () {
    $ana = userWithWallet();
    deposit($ana, '10,00');

    $this->actingAs($ana)->get(route('statement'))
        ->assertOk()
        ->assertSee('Depósito')
        ->assertSee('+R$ 10,00');
});

test('rotula os dois lados da transferência com o nome de quem está do outro lado', function () {
    $ana = userWithWallet(5_000);
    $ana->update(['name' => 'Ana Ribeiro']);
    $bia = userWithWallet();
    $bia->update(['name' => 'Bia Costa']);

    transfer($ana, $bia, '30,00');

    $this->actingAs($ana)->get(route('statement'))
        ->assertSee('Transferência enviada para Bia Costa')
        ->assertSee('-R$ 30,00');

    $this->actingAs($bia)->get(route('statement'))
        ->assertSee('Transferência recebida de Ana Ribeiro')
        ->assertSee('+R$ 30,00');
});

test('mostra crédito com sinal positivo e débito com sinal negativo', function () {
    $ana = userWithWallet(5_000);
    $bia = userWithWallet();

    deposit($ana, '10,00');
    transfer($ana, $bia, '30,00');

    expect(valoresNaTela($this->actingAs($ana)->get(route('statement'))->getContent()))
        ->toBe(['-R$ 30,00', '+R$ 10,00']);
});

test('rotula o estorno de um depósito', function () {
    $ana = userWithWallet();
    $deposito = deposit($ana, '10,00');

    reverseDirectly($deposito);

    $this->actingAs($ana)->get(route('statement'))
        ->assertOk()
        ->assertSee('Estorno de depósito')
        ->assertSee('-R$ 10,00');
});

test('rotula o estorno para quem tinha enviado, nomeando quem ia receber', function () {
    $ana = userWithWallet(5_000);
    $bia = userWithWallet();
    $bia->update(['name' => 'Bia Costa']);

    reverseDirectly(transfer($ana, $bia, '30,00'));

    $html = $this->actingAs($ana)->get(route('statement'))->getContent();

    // A Ana recebe o dinheiro de volta: para ela o estorno é um crédito.
    expect(valoresNaTela($html))->toBe(['+R$ 30,00', '-R$ 30,00'])
        ->and($html)->toContain('Estorno de transferência enviada para Bia Costa');

    // O prefixo sozinho não basta: se o nome sumir, o rótulo termina logo
    // depois de "enviada" e é exatamente isso que este teste precisa pegar.
    expect($html)->not->toMatch('/Estorno de transferência enviada(?! para Bia Costa)/u');
});

test('rotula o estorno para quem tinha recebido, nomeando quem enviou', function () {
    $ana = userWithWallet(5_000);
    $ana->update(['name' => 'Ana Ribeiro']);
    $bia = userWithWallet();

    reverseDirectly(transfer($ana, $bia, '30,00'));

    // O texto exato que a SPEC exige: quem recebeu precisa entender por que o
    // valor saiu, e isso depende de saber de quem ele tinha vindo.
    $this->actingAs($bia)->get(route('statement'))
        ->assertOk()
        ->assertSee('Estorno de transferência recebida de Ana Ribeiro')
        ->assertSee('-R$ 30,00');
});

test('apresenta a situação concluída e a estornada', function () {
    $ana = userWithWallet();
    deposit($ana, '10,00');

    $this->actingAs($ana)->get(route('statement'))
        ->assertSee('Concluída')
        ->assertDontSee('Estornada');

    reverseDirectly(deposit($ana, '20,00'));

    $html = $this->actingAs($ana)->get(route('statement'))->getContent();

    // O depósito estornado passa a "Estornada"; o estorno em si é "Concluída".
    expect(substr_count($html, 'Estornada'))->toBe(1)
        ->and(substr_count($html, 'Concluída'))->toBe(2);
});

test('mantém original e estorno visíveis no extrato', function () {
    $ana = userWithWallet(5_000);
    $bia = userWithWallet();

    reverseDirectly(transfer($ana, $bia, '30,00'));

    $daBia = $this->actingAs($bia)->get(route('statement'))->getContent();

    expect($daBia)->toContain('Transferência recebida de')
        ->and($daBia)->toContain('Estorno de transferência recebida de')
        ->and(valoresNaTela($daBia))->toBe(['-R$ 30,00', '+R$ 30,00']);
});

test('monta os rótulos de estorno sem uma consulta por lançamento', function () {
    // Quantas consultas o extrato faz não pode depender de quantos estornos
    // ele mostra. Sem o carregamento antecipado o rótulo sai igual, só que
    // cobrando uma consulta por linha — e é isso que este teste pega.
    $consultasPara = function (int $quantidade): int {
        $remetente = userWithWallet(500_000);

        foreach (range(1, $quantidade) as $i) {
            $destinatario = userWithWallet();
            $destinatario->update(['name' => 'Pessoa '.$i]);
            reverseDirectly(transfer($remetente, $destinatario, '1,00'));
        }

        $consultas = 0;
        $contador = function () use (&$consultas) {
            $consultas++;
        };

        DB::listen($contador);
        $this->actingAs($remetente)->get(route('statement'))->assertOk();

        return $consultas;
    };

    expect($consultasPara(6))->toBe($consultasPara(2));
});
