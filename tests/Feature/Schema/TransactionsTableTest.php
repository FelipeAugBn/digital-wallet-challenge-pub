<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

require_once __DIR__.'/helpers.php';

test('tem as colunas esperadas', function () {
    expect(Schema::hasTable('transactions'))->toBeTrue()
        ->and(Schema::hasColumns('transactions', [
            'id',
            'type',
            'status',
            'amount',
            'initiated_by_user_id',
            'source_wallet_id',
            'destination_wallet_id',
            'original_transaction_id',
            'reversal_reason',
            'idempotency_key',
            'created_at',
            'updated_at',
        ]))->toBeTrue();
});

test('usa os tipos do PostgreSQL que a especificação exige', function () {
    $types = DB::table('information_schema.columns')
        ->where('table_name', 'transactions')
        ->pluck('data_type', 'column_name');

    expect($types['id'])->toBe('uuid')
        ->and($types['original_transaction_id'])->toBe('uuid')
        ->and($types['idempotency_key'])->toBe('uuid')
        ->and($types['amount'])->toBe('bigint')
        ->and($types['type'])->toBe('character varying')
        ->and($types['status'])->toBe('character varying');
});

test('recusa valor zero ou negativo', function () {
    $user = User::factory()->create();

    foreach ([0, -1] as $amount) {
        expectConstraintViolation('23514', fn () => insertTransaction([
            'amount' => $amount,
            'initiated_by_user_id' => $user->id,
        ]));
    }

    expect(DB::table('transactions')->count())->toBe(0);
});

test('recusa depósito ou transferência sem quem iniciou', function () {
    foreach (['deposit', 'transfer'] as $type) {
        expectConstraintViolation('23514', fn () => insertTransaction([
            'type' => $type,
            'initiated_by_user_id' => null,
        ]));
    }

    expect(DB::table('transactions')->count())->toBe(0);
});

test('aceita estorno sem quem iniciou', function () {
    $id = insertTransaction([
        'type' => 'reversal',
        'initiated_by_user_id' => null,
        'reversal_reason' => 'inconsistency',
    ]);

    expect(DB::table('transactions')->where('id', $id)->exists())->toBeTrue();
});

test('recusa valores fora dos enums', function () {
    $user = User::factory()->create();

    $invalid = [
        ['type' => 'withdrawal'],
        ['status' => 'pending'],
        ['reversal_reason' => 'because'],
    ];

    foreach ($invalid as $attributes) {
        expectConstraintViolation('23514', fn () => insertTransaction(
            $attributes + ['initiated_by_user_id' => $user->id]
        ));
    }

    expect(DB::table('transactions')->count())->toBe(0);
});

test('recusa dois estornos apontando para a mesma transação original', function () {
    $user = User::factory()->create();
    $original = insertTransaction(['initiated_by_user_id' => $user->id]);

    insertTransaction([
        'type' => 'reversal',
        'initiated_by_user_id' => $user->id,
        'original_transaction_id' => $original,
    ]);

    expectConstraintViolation('23505', fn () => insertTransaction([
        'type' => 'reversal',
        'initiated_by_user_id' => $user->id,
        'original_transaction_id' => $original,
    ]));

    expect(DB::table('transactions')->where('original_transaction_id', $original)->count())->toBe(1);
});

test('recusa a mesma chave de idempotência para o mesmo usuário', function () {
    $user = User::factory()->create();
    $key = (string) Str::uuid();

    insertTransaction(['initiated_by_user_id' => $user->id, 'idempotency_key' => $key]);

    expectConstraintViolation('23505', fn () => insertTransaction([
        'initiated_by_user_id' => $user->id,
        'idempotency_key' => $key,
    ]));

    expect(DB::table('transactions')->where('idempotency_key', $key)->count())->toBe(1);
});

test('aceita a mesma chave de idempotência para usuários diferentes', function () {
    $key = (string) Str::uuid();

    foreach (User::factory()->count(2)->create() as $user) {
        insertTransaction(['initiated_by_user_id' => $user->id, 'idempotency_key' => $key]);
    }

    expect(DB::table('transactions')->where('idempotency_key', $key)->count())->toBe(2);
});

test('aceita várias linhas sem chave de idempotência', function () {
    $user = User::factory()->create();

    insertTransaction(['initiated_by_user_id' => $user->id, 'idempotency_key' => null]);
    insertTransaction(['initiated_by_user_id' => $user->id, 'idempotency_key' => null]);

    expect(DB::table('transactions')->whereNull('idempotency_key')->count())->toBe(2);
});

test('mantém o índice de idempotência único, parcial e por usuário', function () {
    $definition = DB::table('pg_indexes')
        ->where('indexname', 'transactions_user_idempotency_key_unique')
        ->value('indexdef');

    expect($definition)->toContain('CREATE UNIQUE INDEX')
        ->and($definition)->toContain('(initiated_by_user_id, idempotency_key)')
        ->and($definition)->toContain('WHERE (idempotency_key IS NOT NULL)');
});

test('indexa as colunas usadas para procurar transações', function () {
    $indexes = DB::table('pg_indexes')
        ->where('tablename', 'transactions')
        ->pluck('indexdef', 'indexname');

    expect($indexes['transactions_initiated_by_user_id_index'])->toContain('(initiated_by_user_id)')
        ->and($indexes['transactions_source_wallet_id_index'])->toContain('(source_wallet_id)')
        ->and($indexes['transactions_destination_wallet_id_index'])->toContain('(destination_wallet_id)');
});

test('recusa apagar qualquer coisa para a qual uma transação ainda aponta', function () {
    $wallet = createWallet();
    $other = createWallet();

    $original = insertTransaction([
        'initiated_by_user_id' => $wallet->user_id,
        'source_wallet_id' => $wallet->id,
        'destination_wallet_id' => $other->id,
    ]);

    insertTransaction([
        'type' => 'reversal',
        'initiated_by_user_id' => $wallet->user_id,
        'original_transaction_id' => $original,
    ]);

    expectConstraintViolation('23001', fn () => DB::table('users')->where('id', $wallet->user_id)->delete());
    expectConstraintViolation('23001', fn () => DB::table('wallets')->where('id', $wallet->id)->delete());
    expectConstraintViolation('23001', fn () => DB::table('wallets')->where('id', $other->id)->delete());
    expectConstraintViolation('23001', fn () => DB::table('transactions')->where('id', $original)->delete());

    expect(DB::table('transactions')->count())->toBe(2);
});
