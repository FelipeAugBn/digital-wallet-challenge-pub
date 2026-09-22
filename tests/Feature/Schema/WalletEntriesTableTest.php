<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require_once __DIR__.'/helpers.php';

/**
 * Insere um lancamento sem passar pelo Model, para exercitar o banco direto.
 *
 * @param  array<string, mixed>  $attributes
 * @return int O id da linha criada.
 */
function insertEntry(array $attributes): int
{
    return DB::table('wallet_entries')->insertGetId(array_merge([
        'type' => 'credit',
        'amount' => 1_000,
        'balance_after' => 1_000,
        'created_at' => now(),
        'updated_at' => now(),
    ], $attributes));
}

it('has the expected columns', function () {
    expect(Schema::hasTable('wallet_entries'))->toBeTrue()
        ->and(Schema::hasColumns('wallet_entries', [
            'id',
            'transaction_id',
            'wallet_id',
            'type',
            'amount',
            'balance_after',
            'created_at',
            'updated_at',
        ]))->toBeTrue();
});

it('keeps every money column as a signed bigint', function () {
    $types = DB::table('information_schema.columns')
        ->where('table_name', 'wallet_entries')
        ->pluck('data_type', 'column_name');

    expect($types['amount'])->toBe('bigint')
        ->and($types['balance_after'])->toBe('bigint')
        ->and($types['transaction_id'])->toBe('uuid')
        ->and($types)->not->toContain('numeric')
        ->and($types)->not->toContain('double precision');
});

it('accepts a negative balance after a reversal', function () {
    $wallet = createWallet();
    $transaction = insertTransaction(['initiated_by_user_id' => $wallet->user_id]);

    $id = insertEntry([
        'transaction_id' => $transaction,
        'wallet_id' => $wallet->id,
        'type' => 'debit',
        'balance_after' => -1_500,
    ]);

    expect(DB::table('wallet_entries')->where('id', $id)->value('balance_after'))->toBe(-1_500);
});

it('refuses an amount of zero or less', function () {
    $wallet = createWallet();
    $transaction = insertTransaction(['initiated_by_user_id' => $wallet->user_id]);

    foreach ([0, -1] as $amount) {
        expectConstraintViolation('23514', fn () => insertEntry([
            'transaction_id' => $transaction,
            'wallet_id' => $wallet->id,
            'amount' => $amount,
        ]));
    }

    expect(DB::table('wallet_entries')->count())->toBe(0);
});

it('refuses a type outside credit and debit', function () {
    $wallet = createWallet();
    $transaction = insertTransaction(['initiated_by_user_id' => $wallet->user_id]);

    expectConstraintViolation('23514', fn () => insertEntry([
        'transaction_id' => $transaction,
        'wallet_id' => $wallet->id,
        'type' => 'chargeback',
    ]));

    expect(DB::table('wallet_entries')->count())->toBe(0);
});

it('refuses the same entry twice for one transaction, wallet and type', function () {
    $wallet = createWallet();
    $transaction = insertTransaction(['initiated_by_user_id' => $wallet->user_id]);

    insertEntry(['transaction_id' => $transaction, 'wallet_id' => $wallet->id, 'type' => 'credit']);

    expectConstraintViolation('23505', fn () => insertEntry([
        'transaction_id' => $transaction,
        'wallet_id' => $wallet->id,
        'type' => 'credit',
    ]));

    // A unicidade inclui o tipo, então o banco aceita o lançamento oposto para o mesmo par.
    insertEntry(['transaction_id' => $transaction, 'wallet_id' => $wallet->id, 'type' => 'debit']);

    expect(DB::table('wallet_entries')->count())->toBe(2);
});

it('indexes the statement in the order it is read', function () {
    $definition = DB::table('pg_indexes')
        ->where('indexname', 'wallet_entries_statement_index')
        ->value('indexdef');

    expect($definition)->toContain('(wallet_id, created_at DESC, id DESC)');
});

it('refuses to delete a wallet or a transaction that still has entries', function () {
    $wallet = createWallet();
    $transaction = insertTransaction(['initiated_by_user_id' => $wallet->user_id]);

    insertEntry(['transaction_id' => $transaction, 'wallet_id' => $wallet->id]);

    expectConstraintViolation('23001', fn () => DB::table('wallets')->where('id', $wallet->id)->delete());
    expectConstraintViolation('23001', fn () => DB::table('transactions')->where('id', $transaction)->delete());

    expect(DB::table('wallet_entries')->count())->toBe(1);
});
