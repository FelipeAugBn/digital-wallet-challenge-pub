<?php

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\WalletEntryType;
use App\Models\Transaction;
use App\Models\WalletEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

require_once __DIR__.'/helpers.php';

it('creates one transaction and one credit entry', function () {
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

it('leaves the source empty and points the destination at the own wallet', function () {
    $user = userWithWallet();

    $transaction = deposit($user, '10,00');

    expect($transaction->source_wallet_id)->toBeNull()
        ->and($transaction->destination_wallet_id)->toBe(walletOf($user)->id);
});

it('writes the new balance both on the wallet and on the entry', function () {
    $user = userWithWallet(2_500);

    deposit($user, '10,00');

    expect(walletOf($user)->balance)->toBe(3_500)
        ->and(WalletEntry::sole()->balance_after)->toBe(3_500);
});

it('adds the deposit on top of a negative balance', function () {
    $user = userWithWallet(-5_000);

    deposit($user, '30,00');

    expect(walletOf($user)->balance)->toBe(-2_000)
        ->and(WalletEntry::sole()->balance_after)->toBe(-2_000);
});

it('keeps each deposit balance_after in step with the wallet', function () {
    $user = userWithWallet();

    deposit($user, '10,00');
    deposit($user, '5,50');

    expect(walletOf($user)->balance)->toBe(1_550)
        ->and(WalletEntry::orderBy('id')->pluck('balance_after')->all())->toBe([1_000, 1_550]);
});

it('locks the wallet only after trying to insert the transaction', function () {
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

it('undoes everything when a failure happens after the transaction row exists', function () {
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
