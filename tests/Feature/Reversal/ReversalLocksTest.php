<?php

use App\Enums\TransactionType;
use App\Exceptions\AlreadyReversed;
use App\Models\Transaction;

require_once __DIR__.'/helpers.php';

test('bloqueia a transação original antes de qualquer outra coisa', function () {
    $ana = userWithWallet(5_000);
    $bia = userWithWallet();
    $transferencia = transfer($ana, $bia, '30,00');

    $consultas = consultasDe(fn () => reverse($transferencia, $ana));

    $locks = posicoesCom($consultas, 'for update');
    $primeiro = $consultas[$locks[0]];

    expect($locks)->toHaveCount(3)
        ->and($primeiro['sql'])->toContain('from "transactions"')
        ->and($primeiro['bindings'])->toContain($transferencia->id);
});

test('bloqueia as carteiras depois da original, por ID crescente', function (bool $origemMaior) {
    // Os dois sentidos: o que decide a ordem do lock é o ID da carteira, nunca
    // qual delas mandou o dinheiro.
    if ($origemMaior) {
        $bia = userWithWallet();
        $ana = userWithWallet(5_000);
    } else {
        $ana = userWithWallet(5_000);
        $bia = userWithWallet();
    }

    $transferencia = transfer($ana, $bia, '30,00');

    $consultas = consultasDe(fn () => reverse($transferencia, $ana));

    $carteiras = posicoesCom($consultas, 'for update', 'from "wallets"');
    $original = posicoesCom($consultas, 'for update', 'from "transactions"');

    $bloqueadas = array_map(fn (int $posicao) => (int) $consultas[$posicao]['bindings'][0], $carteiras);

    expect($carteiras)->toHaveCount(2)
        ->and($carteiras[0])->toBeGreaterThan($original[0])
        ->and($bloqueadas)->toBe([
            min(walletOf($ana)->id, walletOf($bia)->id),
            max(walletOf($ana)->id, walletOf($bia)->id),
        ]);
})->with(['origem com ID menor' => false, 'origem com ID maior' => true]);

test('insere a transação de estorno somente depois dos locks', function () {
    $ana = userWithWallet(5_000);
    $bia = userWithWallet();
    $transferencia = transfer($ana, $bia, '30,00');

    $consultas = consultasDe(fn () => reverse($transferencia, $ana));

    $insercao = posicoesCom($consultas, 'insert into "transactions"');
    $locks = posicoesCom($consultas, 'for update');

    // O contrário do depósito e da transferência, de propósito: lá a inserção
    // vem antes para recusar uma repetição sem segurar carteira; aqui só se
    // sabe se o estorno pode existir depois de ler a original bloqueada.
    expect($insercao)->toHaveCount(1)
        ->and($insercao[0])->toBeGreaterThan(max($locks));
});

test('recusa a segunda tentativa pelo status relido sob lock, sem tentar inserir', function () {
    $ana = userWithWallet(5_000);
    $bia = userWithWallet();
    $transferencia = transfer($ana, $bia, '30,00');

    reverse($transferencia, $ana);

    $consultas = consultasDe(function () use ($transferencia, $ana) {
        expect(fn () => reverse($transferencia, $ana))->toThrow(AlreadyReversed::class);
    });

    // Nenhuma inserção significa que nenhuma violação de unicidade participou
    // da recusa: quem recusou foi o status lido da linha bloqueada.
    expect(posicoesCom($consultas, 'insert into'))->toBeEmpty()
        ->and(posicoesCom($consultas, 'for update', 'from "wallets"'))->toBeEmpty()
        ->and(posicoesCom($consultas, 'for update', 'from "transactions"'))->toHaveCount(1)
        ->and(Transaction::query()->where('type', TransactionType::Reversal)->count())->toBe(1);
});
