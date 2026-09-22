<?php

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Executa a violacao dentro de um savepoint para que a conexao continue
 * utilizavel depois do erro do PostgreSQL.
 */
function expectConstraintViolation(string $sqlState, Closure $operation): void
{
    try {
        DB::transaction($operation);
    } catch (QueryException $exception) {
        expect($exception->getCode())->toBe($sqlState);

        return;
    }

    test()->fail('O PostgreSQL aceitou uma operacao que deveria ser recusada.');
}

it('has the expected columns', function () {
    expect(Schema::hasTable('wallets'))->toBeTrue()
        ->and(Schema::hasColumns('wallets', ['id', 'user_id', 'balance', 'created_at', 'updated_at']))->toBeTrue();
});

it('stores the balance as a signed bigint defaulting to zero', function () {
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

it('refuses a second wallet for the same user at the database level', function () {
    $user = User::factory()->create();
    Wallet::create(['user_id' => $user->id]);

    expectConstraintViolation('23505', fn () => DB::table('wallets')->insert([
        'user_id' => $user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]));

    expect(Wallet::where('user_id', $user->id)->count())->toBe(1);
});

it('refuses to delete a user that still owns a wallet', function () {
    $user = User::factory()->create();
    $wallet = Wallet::create(['user_id' => $user->id]);

    expectConstraintViolation('23001', fn () => DB::table('users')->where('id', $user->id)->delete());

    expect(User::whereKey($user->id)->exists())->toBeTrue()
        ->and(Wallet::whereKey($wallet->id)->exists())->toBeTrue();
});
