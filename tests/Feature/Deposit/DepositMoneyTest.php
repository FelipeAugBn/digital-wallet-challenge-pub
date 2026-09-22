<?php

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\WalletEntryType;
use App\Models\Transaction;
use App\Models\WalletEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

require_once __DIR__.'/helpers.php';

test('cria uma transação e um lançamento de crédito', function () {
    $user = userWithWallet();

    $transaction = deposit($user, '1.000,50');

    expect(Transaction::count())->toBe(1)
        ->and(WalletEntry::count())->toBe(1)
        ->and($transaction->type)->toBe(TransactionType::Deposit)
        ->and($transaction->status)->toBe(TransactionStatus::Completed)
        ->and($transaction->amount)->toBe(100_050)
        ->and($transaction->initiated_by_user_id)->toBe($user->id);

    $entry = WalletEntry::sole();

    expect($entry->type)->toBe(WalletEntryType::Credit)
        ->and($entry->transaction_id)->toBe($transaction->id)
        ->and($entry->wallet_id)->toBe(walletOf($user)->id)
        ->and($entry->amount)->toBe(100_050);
});

test('deixa a origem vazia e aponta o destino para a própria carteira', function () {
    $user = userWithWallet();

    $transaction = deposit($user, '10,00');

    expect($transaction->source_wallet_id)->toBeNull()
        ->and($transaction->destination_wallet_id)->toBe(walletOf($user)->id);
});

test('grava o novo saldo na carteira e no lançamento', function () {
    $user = userWithWallet(2_500);

    deposit($user, '10,00');

    expect(walletOf($user)->balance)->toBe(3_500)
        ->and(WalletEntry::sole()->balance_after)->toBe(3_500);
});

test('soma o depósito sobre saldo negativo', function () {
    $user = userWithWallet(-5_000);

    deposit($user, '30,00');

    expect(walletOf($user)->balance)->toBe(-2_000)
        ->and(WalletEntry::sole()->balance_after)->toBe(-2_000);
});

test('mantém o balance_after de cada depósito alinhado com a carteira', function () {
    $user = userWithWallet();

    deposit($user, '10,00');
    deposit($user, '5,50');

    expect(walletOf($user)->balance)->toBe(1_550)
        ->and(WalletEntry::orderBy('id')->pluck('balance_after')->all())->toBe([1_000, 1_550]);
});

test('bloqueia a carteira só depois de tentar inserir a transação', function () {
    $user = userWithWallet();
    $queries = [];

    DB::listen(function ($query) use (&$queries) {
        $queries[] = $query->sql;
    });

    deposit($user, '10,00');

    $insert = null;
    $lock = null;

    foreach ($queries as $posicao => $sql) {
        if ($insert === null && str_contains($sql, 'insert into "transactions"')) {
            $insert = $posicao;
        }

        if ($lock === null && str_contains($sql, 'for update')) {
            $lock = $posicao;
        }
    }

    expect($insert)->not->toBeNull()
        ->and($lock)->not->toBeNull()
        ->and($insert)->toBeLessThan($lock)
        ->and($queries[$lock])->toContain('from "wallets"');
});

test('desfaz tudo quando a falha acontece depois de a transação existir', function () {
    $user = userWithWallet(2_500);

    // O listener vive no dispatcher desta aplicacao de teste, que e recriada a
    // cada cenario, entao nao sobra nada para os testes seguintes.
    Event::listen('eloquent.creating: '.WalletEntry::class, function () {
        throw new RuntimeException('falha depois de gravar a operacao');
    });

    expect(fn () => deposit($user, '10,00'))->toThrow(RuntimeException::class);

    expect(Transaction::count())->toBe(0)
        ->and(WalletEntry::count())->toBe(0)
        ->and(walletOf($user)->balance)->toBe(2_500);
});
