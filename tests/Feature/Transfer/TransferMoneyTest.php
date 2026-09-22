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

it('creates one transaction and the two entries that belong to it', function () {
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

it('writes a debit on the sender and a credit on the recipient', function () {
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

it('records the debit before the credit', function () {
    $ana = userWithWallet(5_000);
    $bia = userWithWallet();

    transfer($ana, $bia, '30,00');

    expect(WalletEntry::orderBy('id')->pluck('type')->all())
        ->toBe([WalletEntryType::Debit, WalletEntryType::Credit]);
});

it('updates both balances in the same operation', function () {
    $ana = userWithWallet(5_000);
    $bia = userWithWallet(1_000);

    transfer($ana, $bia, '30,00');

    expect(walletOf($ana)->balance)->toBe(2_000)
        ->and(walletOf($bia)->balance)->toBe(4_000);
});

it('accepts a balance that is exactly the amount and leaves zero behind', function () {
    $ana = userWithWallet(3_000);
    $bia = userWithWallet();

    transfer($ana, $bia, '30,00');

    expect(walletOf($ana)->balance)->toBe(0)
        ->and(WalletEntry::query()->where('wallet_id', walletOf($ana)->id)->sole()->balance_after)->toBe(0)
        ->and(walletOf($bia)->balance)->toBe(3_000);
});

it('refuses one cent more than the balance and changes nothing', function () {
    $ana = userWithWallet(3_000);
    $bia = userWithWallet(500);

    expect(fn () => transfer($ana, $bia, '30,01'))->toThrow(InsufficientFunds::class);

    expect(Transaction::count())->toBe(0)
        ->and(WalletEntry::count())->toBe(0)
        ->and(walletOf($ana)->balance)->toBe(3_000)
        ->and(walletOf($bia)->balance)->toBe(500);
});

it('credits a recipient whose balance is negative', function () {
    $ana = userWithWallet(5_000);
    $bia = userWithWallet(-2_000);

    transfer($ana, $bia, '30,00');

    expect(walletOf($bia)->balance)->toBe(1_000)
        ->and(WalletEntry::query()->where('wallet_id', walletOf($bia)->id)->sole()->balance_after)->toBe(1_000);
});

it('refuses a recipient that nobody uses', function () {
    $ana = userWithWallet(5_000);

    expect(fn () => transfer($ana, 'ninguem@exemplo.com', '30,00'))->toThrow(RecipientNotFound::class);

    expect(Transaction::count())->toBe(0)
        ->and(WalletEntry::count())->toBe(0)
        ->and(walletOf($ana)->balance)->toBe(5_000);
});

it('refuses a transfer to the sender own wallet', function () {
    $ana = userWithWallet(5_000);

    expect(fn () => transfer($ana, $ana, '30,00'))->toThrow(TransferToSelf::class);

    expect(Transaction::count())->toBe(0)
        ->and(WalletEntry::count())->toBe(0)
        ->and(walletOf($ana)->balance)->toBe(5_000);
});

it('inserts the transaction before taking the first lock', function () {
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

it('locks both wallets from the smaller id to the bigger one', function (bool $origemMaior) {
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
    'origem com id menor' => false,
    'origem com id maior' => true,
]);

it('undoes transaction, both entries and both balances when the last step fails', function () {
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
