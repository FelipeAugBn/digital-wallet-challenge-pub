<?php

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

/**
 * Carteira nova com o dono correspondente.
 */
function createWallet(): Wallet
{
    return Wallet::create(['user_id' => User::factory()->create()->id]);
}

/**
 * Insere uma transacao sem passar pelo Model, para exercitar o banco direto.
 *
 * @param  array<string, mixed>  $attributes
 * @return string O UUID da linha criada.
 */
function insertTransaction(array $attributes = []): string
{
    $id = $attributes['id'] ?? (string) Str::uuid7();

    DB::table('transactions')->insert(array_merge([
        'type' => 'deposit',
        'status' => 'completed',
        'amount' => 1_000,
        'created_at' => now(),
        'updated_at' => now(),
    ], $attributes, ['id' => $id]));

    return $id;
}
