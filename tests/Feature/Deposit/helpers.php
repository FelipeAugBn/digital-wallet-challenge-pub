<?php

use App\Actions\DepositMoney;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Support\Money;
use Illuminate\Support\Str;

/**
 * Pessoa com carteira, como o cadastro entrega.
 *
 * O saldo e escrito pelo builder porque ele fica fora do `$fillable`.
 */
function userWithWallet(int $balance = 0): User
{
    $user = User::factory()->create();
    $wallet = Wallet::create(['user_id' => $user->id]);

    if ($balance !== 0) {
        Wallet::query()->whereKey($wallet->id)->update(['balance' => $balance]);
    }

    return $user;
}

/** A carteira da pessoa, sempre relida do banco. */
function walletOf(User $user): Wallet
{
    return Wallet::query()->where('user_id', $user->id)->sole();
}

/**
 * Grava uma operacao direto, para que o deposito seguinte encontre a chave ja
 * ocupada e precise passar pelo caminho real da violacao de unicidade.
 *
 * @param  array<string, mixed>  $attributes
 */
function persistedTransaction(User $user, string $idempotencyKey, array $attributes = []): Transaction
{
    return Transaction::create(array_merge([
        'type' => TransactionType::Deposit,
        'status' => TransactionStatus::Completed,
        'amount' => 1_000,
        'initiated_by_user_id' => $user->id,
        'destination_wallet_id' => walletOf($user)->id,
        'idempotency_key' => $idempotencyKey,
    ], $attributes));
}

/** Chama a Action pelo container, como o controller faz. */
function deposit(User $user, string $amount, ?string $key = null): Transaction
{
    return app(DepositMoney::class)->handle(
        $user,
        Money::fromInput($amount),
        $key ?? (string) Str::uuid7(),
    );
}
