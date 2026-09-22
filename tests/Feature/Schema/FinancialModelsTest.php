<?php

use App\Enums\ReversalReason;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\WalletEntryType;
use App\Models\Transaction;
use App\Models\WalletEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require_once __DIR__.'/helpers.php';

/**
 * Deposito minimo valido, ja persistido pelo Model.
 *
 * @param  array<string, mixed>  $attributes
 */
function makeTransaction(array $attributes = []): Transaction
{
    $attributes['initiated_by_user_id'] ??= createWallet()->user_id;

    return Transaction::create(array_merge([
        'type' => TransactionType::Deposit,
        'status' => TransactionStatus::Completed,
        'amount' => 5_000,
    ], $attributes));
}

it('generates the identifier on the server as a version 7 uuid', function () {
    $transaction = makeTransaction();

    expect($transaction->id)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/')
        ->and($transaction->getKeyType())->toBe('string')
        ->and($transaction->getIncrementing())->toBeFalse();
});

it('ignores an identifier coming from the outside', function () {
    $chosen = (string) Str::uuid7();

    $transaction = makeTransaction(['id' => $chosen]);

    expect(Transaction::query()->getModel()->getFillable())->not->toContain('id')
        ->and($transaction->id)->not->toBe($chosen);
});

it('generates identifiers that sort in the order they were created', function () {
    $ids = [];

    for ($i = 0; $i < 3; $i++) {
        $ids[] = makeTransaction()->id;
        usleep(2_000);
    }

    $sorted = $ids;
    sort($sorted);

    expect($sorted)->toBe($ids);
});

it('casts the transaction columns back into enums and integers', function () {
    $original = makeTransaction();

    $reversal = makeTransaction([
        'type' => TransactionType::Reversal,
        'status' => TransactionStatus::Completed,
        'reversal_reason' => ReversalReason::UserRequest,
        'original_transaction_id' => $original->id,
    ]);

    $original->update(['status' => TransactionStatus::Reversed]);

    $fresh = $reversal->fresh();

    expect($fresh->type)->toBe(TransactionType::Reversal)
        ->and($fresh->status)->toBe(TransactionStatus::Completed)
        ->and($fresh->reversal_reason)->toBe(ReversalReason::UserRequest)
        ->and($fresh->amount)->toBeInt()
        ->and($original->fresh()->status)->toBe(TransactionStatus::Reversed)
        ->and(makeTransaction()->fresh()->reversal_reason)->toBeNull();
});

it('links a transaction to its people, wallets and reversal', function () {
    $source = createWallet();
    $destination = createWallet();

    $original = makeTransaction([
        'type' => TransactionType::Transfer,
        'initiated_by_user_id' => $source->user_id,
        'source_wallet_id' => $source->id,
        'destination_wallet_id' => $destination->id,
    ]);

    $reversal = makeTransaction([
        'type' => TransactionType::Reversal,
        'initiated_by_user_id' => $source->user_id,
        'original_transaction_id' => $original->id,
    ]);

    expect($original->initiatedBy->is($source->user))->toBeTrue()
        ->and($original->sourceWallet->is($source))->toBeTrue()
        ->and($original->destinationWallet->is($destination))->toBeTrue()
        ->and($original->reversal->is($reversal))->toBeTrue()
        ->and($reversal->originalTransaction->is($original))->toBeTrue()
        ->and($source->user->initiatedTransactions)->toHaveCount(2)
        ->and($source->outgoingTransactions->pluck('id')->all())->toBe([$original->id])
        ->and($destination->incomingTransactions->pluck('id')->all())->toBe([$original->id]);
});

it('casts the entry columns and links it to the transaction and the wallet', function () {
    $wallet = createWallet();
    $transaction = makeTransaction([
        'initiated_by_user_id' => $wallet->user_id,
        'destination_wallet_id' => $wallet->id,
    ]);

    $entry = WalletEntry::create([
        'transaction_id' => $transaction->id,
        'wallet_id' => $wallet->id,
        'type' => WalletEntryType::Credit,
        'amount' => 5_000,
        'balance_after' => -1_000,
    ])->fresh();

    expect($entry->type)->toBe(WalletEntryType::Credit)
        ->and($entry->amount)->toBe(5_000)
        ->and($entry->balance_after)->toBe(-1_000)
        ->and($entry->transaction->is($transaction))->toBeTrue()
        ->and($entry->wallet->is($wallet))->toBeTrue()
        ->and($transaction->entries)->toHaveCount(1)
        ->and($wallet->entries)->toHaveCount(1);
});

it('keeps the php enums and the database checks in sync', function () {
    $checks = fn (string $table) => DB::table('pg_constraint')
        ->selectRaw('conname, pg_get_constraintdef(oid) as definition')
        ->whereRaw('conrelid = ?::regclass', [$table])
        ->where('contype', 'c')
        ->pluck('definition', 'conname');

    $definitions = $checks('transactions')->merge($checks('wallet_entries'));

    $accepted = [
        'transactions_type_valid' => TransactionType::class,
        'transactions_status_valid' => TransactionStatus::class,
        'transactions_reversal_reason_valid' => ReversalReason::class,
        'wallet_entries_type_valid' => WalletEntryType::class,
    ];

    foreach ($accepted as $constraint => $enum) {
        foreach ($enum::cases() as $case) {
            expect($definitions[$constraint])->toContain("'{$case->value}'");
        }
    }
});
