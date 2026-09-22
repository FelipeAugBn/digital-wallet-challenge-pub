<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

require_once __DIR__.'/helpers.php';

it('has the expected columns', function () {
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

it('uses the postgresql types the spec requires', function () {
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

it('refuses an amount of zero or less', function () {
    $user = User::factory()->create();

    foreach ([0, -1] as $amount) {
        expectConstraintViolation('23514', fn () => insertTransaction([
            'amount' => $amount,
            'initiated_by_user_id' => $user->id,
        ]));
    }

    expect(DB::table('transactions')->count())->toBe(0);
});

it('refuses a deposit or a transfer without an initiator', function () {
    foreach (['deposit', 'transfer'] as $type) {
        expectConstraintViolation('23514', fn () => insertTransaction([
            'type' => $type,
            'initiated_by_user_id' => null,
        ]));
    }

    expect(DB::table('transactions')->count())->toBe(0);
});

it('accepts a reversal without an initiator', function () {
    $id = insertTransaction([
        'type' => 'reversal',
        'initiated_by_user_id' => null,
        'reversal_reason' => 'inconsistency',
    ]);

    expect(DB::table('transactions')->where('id', $id)->exists())->toBeTrue();
});

it('refuses values outside the enums', function () {
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

it('refuses two reversals pointing at the same original transaction', function () {
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

it('refuses the same idempotency key for the same user', function () {
    $user = User::factory()->create();
    $key = (string) Str::uuid();

    insertTransaction(['initiated_by_user_id' => $user->id, 'idempotency_key' => $key]);

    expectConstraintViolation('23505', fn () => insertTransaction([
        'initiated_by_user_id' => $user->id,
        'idempotency_key' => $key,
    ]));

    expect(DB::table('transactions')->where('idempotency_key', $key)->count())->toBe(1);
});

it('accepts the same idempotency key for different users', function () {
    $key = (string) Str::uuid();

    foreach (User::factory()->count(2)->create() as $user) {
        insertTransaction(['initiated_by_user_id' => $user->id, 'idempotency_key' => $key]);
    }

    expect(DB::table('transactions')->where('idempotency_key', $key)->count())->toBe(2);
});

it('accepts many rows without an idempotency key', function () {
    $user = User::factory()->create();

    insertTransaction(['initiated_by_user_id' => $user->id, 'idempotency_key' => null]);
    insertTransaction(['initiated_by_user_id' => $user->id, 'idempotency_key' => null]);

    expect(DB::table('transactions')->whereNull('idempotency_key')->count())->toBe(2);
});

it('keeps the idempotency index unique, partial and scoped to the user', function () {
    $definition = DB::table('pg_indexes')
        ->where('indexname', 'transactions_user_idempotency_key_unique')
        ->value('indexdef');

    expect($definition)->toContain('CREATE UNIQUE INDEX')
        ->and($definition)->toContain('(initiated_by_user_id, idempotency_key)')
        ->and($definition)->toContain('WHERE (idempotency_key IS NOT NULL)');
});

it('indexes the columns used to look transactions up', function () {
    $indexes = DB::table('pg_indexes')
        ->where('tablename', 'transactions')
        ->pluck('indexdef', 'indexname');

    expect($indexes['transactions_initiated_by_user_id_index'])->toContain('(initiated_by_user_id)')
        ->and($indexes['transactions_source_wallet_id_index'])->toContain('(source_wallet_id)')
        ->and($indexes['transactions_destination_wallet_id_index'])->toContain('(destination_wallet_id)');
});

it('refuses to delete anything a transaction still points at', function () {
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
