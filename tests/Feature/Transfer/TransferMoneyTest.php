<?php

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\WalletEntryType;
use App\Exceptions\InsufficientFunds;
use App\Exceptions\RecipientNotFound;
use App\Exceptions\TransferToSelf;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Models\WalletEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

require_once __DIR__.'/helpers.php';

test('cria uma transação e os dois lançamentos que pertencem a ela', function () {
    $ana = userWithWallet(5_000);
    $bia = userWithWallet();

    $transaction = transfer($ana, $bia, '30,00');

    expect(Transaction::count())->toBe(1)
        ->and(WalletEntry::count())->toBe(2)
        ->and($transaction->type)->toBe(TransactionType::Transfer)
        ->and($transaction->status)->toBe(TransactionStatus::Completed)
        ->and($transaction->amount)->toBe(3_000)
        ->and($transaction->initiated_by_user_id)->toBe($ana->id)
        ->and((int) $transaction->source_wallet_id)->toBe(walletOf($ana)->id)
        ->and((int) $transaction->destination_wallet_id)->toBe(walletOf($bia)->id);
});

test('grava um débito no remetente e um crédito no destinatário', function () {
    $ana = userWithWallet(5_000);
    $bia = userWithWallet(1_000);

    $transaction = transfer($ana, $bia, '30,00');

    $debito = WalletEntry::query()->where('wallet_id', walletOf($ana)->id)->sole();
    $credito = WalletEntry::query()->where('wallet_id', walletOf($bia)->id)->sole();

    expect($debito->type)->toBe(WalletEntryType::Debit)
        ->and($debito->amount)->toBe(3_000)
        ->and($debito->balance_after)->toBe(2_000)
        ->and($debito->transaction_id)->toBe($transaction->id)
        ->and($credito->type)->toBe(WalletEntryType::Credit)
        ->and($credito->amount)->toBe(3_000)
        ->and($credito->balance_after)->toBe(4_000)
        ->and($credito->transaction_id)->toBe($transaction->id);
});

test('registra o débito antes do crédito', function () {
    $ana = userWithWallet(5_000);
    $bia = userWithWallet();

    transfer($ana, $bia, '30,00');

    expect(WalletEntry::orderBy('id')->pluck('type')->all())
        ->toBe([WalletEntryType::Debit, WalletEntryType::Credit]);
});

test('atualiza os dois saldos na mesma operação', function () {
    $ana = userWithWallet(5_000);
    $bia = userWithWallet(1_000);

    transfer($ana, $bia, '30,00');

    expect(walletOf($ana)->balance)->toBe(2_000)
        ->and(walletOf($bia)->balance)->toBe(4_000);
});

test('aceita saldo exatamente igual ao valor e deixa zero', function () {
    $ana = userWithWallet(3_000);
    $bia = userWithWallet();

    transfer($ana, $bia, '30,00');

    expect(walletOf($ana)->balance)->toBe(0)
        ->and(WalletEntry::query()->where('wallet_id', walletOf($ana)->id)->sole()->balance_after)->toBe(0)
        ->and(walletOf($bia)->balance)->toBe(3_000);
});

test('recusa um centavo a mais que o saldo e não altera nada', function () {
    $ana = userWithWallet(3_000);
    $bia = userWithWallet(500);

    expect(fn () => transfer($ana, $bia, '30,01'))->toThrow(InsufficientFunds::class);

    expect(Transaction::count())->toBe(0)
        ->and(WalletEntry::count())->toBe(0)
        ->and(walletOf($ana)->balance)->toBe(3_000)
        ->and(walletOf($bia)->balance)->toBe(500);
});

test('credita destinatário com saldo negativo', function () {
    $ana = userWithWallet(5_000);
    $bia = userWithWallet(-2_000);

    transfer($ana, $bia, '30,00');

    expect(walletOf($bia)->balance)->toBe(1_000)
        ->and(WalletEntry::query()->where('wallet_id', walletOf($bia)->id)->sole()->balance_after)->toBe(1_000);
});

test('recusa destinatário que não pertence a ninguém', function () {
    $ana = userWithWallet(5_000);

    expect(fn () => transfer($ana, 'ninguem@exemplo.com', '30,00'))->toThrow(RecipientNotFound::class);

    expect(Transaction::count())->toBe(0)
        ->and(WalletEntry::count())->toBe(0)
        ->and(walletOf($ana)->balance)->toBe(5_000);
});

test('recusa transferência para a própria carteira do remetente', function () {
    $ana = userWithWallet(5_000);

    expect(fn () => transfer($ana, $ana, '30,00'))->toThrow(TransferToSelf::class);

    expect(Transaction::count())->toBe(0)
        ->and(WalletEntry::count())->toBe(0)
        ->and(walletOf($ana)->balance)->toBe(5_000);
});

test('insere a transação antes do primeiro lock', function () {
    $ana = userWithWallet(5_000);
    $bia = userWithWallet();

    $queries = [];

    DB::listen(function ($query) use (&$queries) {
        $queries[] = $query->sql;
    });

    transfer($ana, $bia, '30,00');

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

test('bloqueia as duas carteiras do menor ID para o maior', function (bool $origemMaior) {
    // A ordem de criacao decide os identificadores, entao o mesmo cenario roda
    // com a origem dos dois lados e a ordem dos locks nao pode mudar.
    if ($origemMaior) {
        $bia = userWithWallet();
        $ana = userWithWallet(5_000);
    } else {
        $ana = userWithWallet(5_000);
        $bia = userWithWallet();
    }

    $locks = [];

    DB::listen(function ($query) use (&$locks) {
        if (str_contains($query->sql, 'for update')) {
            $locks[] = (int) $query->bindings[0];
        }
    });

    transfer($ana, $bia, '30,00');

    $origem = walletOf($ana)->id;
    $destino = walletOf($bia)->id;
    $crescente = [min($origem, $destino), max($origem, $destino)];

    expect($origem > $destino)->toBe($origemMaior)
        ->and($locks)->toBe($crescente);
})->with([
    'origem com ID menor' => false,
    'origem com ID maior' => true,
]);

test('desfaz transação, os dois lançamentos e os dois saldos quando o último passo falha', function () {
    $ana = userWithWallet(5_000);
    $bia = userWithWallet(1_000);
    $destino = walletOf($bia)->id;

    // A falha cai no ultimo passo: a transacao, os dois lancamentos e o saldo
    // da origem ja existem, e nada disso pode sobreviver ao rollback.
    Event::listen('eloquent.saving: '.Wallet::class, function (Wallet $wallet) use ($destino) {
        if ((int) $wallet->id === $destino) {
            throw new RuntimeException('falha depois do debito');
        }
    });

    expect(fn () => transfer($ana, $bia, '30,00'))->toThrow(RuntimeException::class);

    expect(Transaction::count())->toBe(0)
        ->and(WalletEntry::count())->toBe(0)
        ->and(walletOf($ana)->balance)->toBe(5_000)
        ->and(walletOf($bia)->balance)->toBe(1_000);
});
