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

test('tem as colunas esperadas', function () {
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

test('mantém toda coluna de dinheiro como bigint com sinal', function () {
    $types = DB::table('information_schema.columns')
        ->where('table_name', 'wallet_entries')
        ->pluck('data_type', 'column_name');

    expect($types['amount'])->toBe('bigint')
        ->and($types['balance_after'])->toBe('bigint')
        ->and($types['transaction_id'])->toBe('uuid')
        ->and($types)->not->toContain('numeric')
        ->and($types)->not->toContain('double precision');
});

test('aceita saldo negativo depois de um estorno', function () {
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

test('recusa valor zero ou negativo', function () {
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

test('recusa tipo fora de crédito e débito', function () {
    $wallet = createWallet();
    $transaction = insertTransaction(['initiated_by_user_id' => $wallet->user_id]);

    expectConstraintViolation('23514', fn () => insertEntry([
        'transaction_id' => $transaction,
        'wallet_id' => $wallet->id,
        'type' => 'chargeback',
    ]));

    expect(DB::table('wallet_entries')->count())->toBe(0);
});

test('recusa o mesmo lançamento duas vezes para a mesma transação, carteira e tipo', function () {
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

test('indexa o extrato na ordem em que ele é lido', function () {
    $definition = DB::table('pg_indexes')
        ->where('indexname', 'wallet_entries_statement_index')
        ->value('indexdef');

    expect($definition)->toContain('(wallet_id, created_at DESC, id DESC)');
});

test('recusa apagar carteira ou transação que ainda tem lançamentos', function () {
    $wallet = createWallet();
    $transaction = insertTransaction(['initiated_by_user_id' => $wallet->user_id]);

    insertEntry(['transaction_id' => $transaction, 'wallet_id' => $wallet->id]);

    expectConstraintViolation('23001', fn () => DB::table('wallets')->where('id', $wallet->id)->delete());
    expectConstraintViolation('23001', fn () => DB::table('transactions')->where('id', $transaction)->delete());

    expect(DB::table('wallet_entries')->count())->toBe(1);
});
