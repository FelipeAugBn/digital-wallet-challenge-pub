<?php

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require_once __DIR__.'/helpers.php';

test('tem as colunas esperadas', function () {
    expect(Schema::hasTable('wallets'))->toBeTrue()
        ->and(Schema::hasColumns('wallets', ['id', 'user_id', 'balance', 'created_at', 'updated_at']))->toBeTrue();
});

test('guarda o saldo como bigint com sinal e padrão zero', function () {
    $column = DB::selectOne(
        'select data_type, column_default from information_schema.columns where table_name = ? and column_name = ?',
        ['wallets', 'balance']
    );

    expect($column->data_type)->toBe('bigint')
        ->and($column->column_default)->toBe("'0'::bigint");

    $wallet = Wallet::create(['user_id' => User::factory()->create()->id]);
    DB::table('wallets')->where('id', $wallet->id)->update(['balance' => -250]);

    expect($wallet->fresh()->balance)->toBe(-250);
});

test('recusa uma segunda carteira para o mesmo usuário no nível do banco', function () {
    $user = User::factory()->create();
    Wallet::create(['user_id' => $user->id]);

    expectConstraintViolation('23505', fn () => DB::table('wallets')->insert([
        'user_id' => $user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]));

    expect(Wallet::where('user_id', $user->id)->count())->toBe(1);
});

test('recusa apagar usuário que ainda tem carteira', function () {
    $user = User::factory()->create();
    $wallet = Wallet::create(['user_id' => $user->id]);

    expectConstraintViolation('23001', fn () => DB::table('users')->where('id', $user->id)->delete());

    expect(User::whereKey($user->id)->exists())->toBeTrue()
        ->and(Wallet::whereKey($wallet->id)->exists())->toBeTrue();
});
