<?php

use App\Models\Transaction;
use App\Models\Wallet;
use App\Models\WalletEntry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/../Statement/helpers.php';

// Os testes que fixam o relógio o devolvem no fim, mesmo quando a asserção falha.
afterEach(fn () => Carbon::setTestNow());

test('leva o visitante para o login', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('mostra o saldo persistido na carteira, formatado em reais', function () {
    $ana = userWithWallet(123_456);

    $this->actingAs($ana)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('R$ 1.234,56');
});

test('mostra saldo negativo com o sinal na frente', function () {
    $ana = userWithWallet(-5_000);

    $this->actingAs($ana)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('-R$ 50,00');
});

test('mostra o saldo da carteira e não a soma dos lançamentos', function () {
    $ana = userWithWallet();
    deposit($ana, '10,00');
    deposit($ana, '5,00');

    // O saldo oficial é o da carteira. Mexo nele por fora para que somar os
    // lançamentos daria outro número, e a tela precisa mostrar o oficial.
    Wallet::query()->whereKey(walletOf($ana)->id)->update(['balance' => 9_900]);

    $this->actingAs($ana)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('R$ 99,00')
        ->assertDontSee('R$ 15,00');
});

test('mostra o nome da pessoa', function () {
    $ana = userWithWallet();
    $ana->update(['name' => 'Ana Ribeiro']);

    $this->actingAs($ana)->get(route('dashboard'))->assertSee('Ana Ribeiro');
});

test('oferece os atalhos de depósito, transferência e extrato', function () {
    $this->actingAs(userWithWallet())->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('deposits.create'), false)
        ->assertSee(route('transfers.create'), false)
        ->assertSee(route('statement'), false);
});

test('mostra no máximo as 5 movimentações mais recentes', function () {
    $ana = userWithWallet();
    depositosCrescentes($ana, 8);

    $valores = valoresNaTela($this->actingAs($ana)->get(route('dashboard'))->getContent());

    // As cinco maiores, porque foram as últimas a serem feitas.
    expect($valores)->toBe(['+R$ 0,08', '+R$ 0,07', '+R$ 0,06', '+R$ 0,05', '+R$ 0,04']);
});

test('avisa quando ainda não há movimentação', function () {
    $this->actingAs(userWithWallet())->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Nenhuma movimentação ainda.');
});

test('mostra apenas movimentações da própria carteira', function () {
    $ana = userWithWallet(5_000);
    $bia = userWithWallet();

    transfer($ana, $bia, '30,00');

    $this->actingAs($bia)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Transferência recebida de '.$ana->name)
        ->assertDontSee('Transferência enviada');
});

test('abrir o painel não movimenta dinheiro nem trava carteira', function () {
    $ana = userWithWallet(5_000);
    deposit($ana, '10,00');

    $consultas = [];
    DB::listen(function ($query) use (&$consultas) {
        $consultas[] = $query->sql;
    });

    $this->actingAs($ana)->get(route('dashboard'))->assertOk();

    $escritas = array_filter($consultas, fn (string $sql) => (bool) preg_match('/^(insert|update|delete)/i', $sql));

    expect(array_filter($consultas, fn (string $sql) => str_contains($sql, 'for update')))->toBeEmpty()
        ->and($escritas)->toBeEmpty()
        ->and(Transaction::count())->toBe(1)
        ->and(WalletEntry::count())->toBe(1)
        ->and(walletOf($ana)->balance)->toBe(6_000);
});

test('mostra quando foi a última movimentação, com o dia como se fala', function (string $quando, string $esperado) {
    $ana = userWithWallet();

    Carbon::setTestNow($quando);
    deposit($ana, '10,00');

    Carbon::setTestNow('2026-09-23 12:00:00');

    $this->actingAs($ana)->get(route('dashboard'))->assertOk()->assertSee($esperado);
})->with([
    'hoje' => ['2026-09-23 14:05:00', 'Última movimentação hoje às 14:05'],
    'ontem' => ['2026-09-22 09:35:00', 'Última movimentação ontem às 09:35'],
    'mais antiga' => ['2026-09-03 08:15:00', 'Última movimentação 3 de setembro às 08:15'],
]);

test('não fala de última movimentação quando não há nenhuma', function () {
    $this->actingAs(userWithWallet())->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Última movimentação');
});
