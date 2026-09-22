<?php

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Str;

require_once __DIR__.'/helpers.php';

test('ignora saldo enviado na criação da carteira', function () {
    $user = User::factory()->create();

    $wallet = Wallet::create(['user_id' => $user->id, 'balance' => 999_999]);

    expect($wallet->refresh()->balance)->toBe(0);
});

test('ignora identificador forjado na criação da transação', function () {
    $user = userWithWallet();

    $transaction = Transaction::create([
        'id' => '00000000-0000-7000-8000-000000000000',
        'type' => TransactionType::Deposit,
        'status' => TransactionStatus::Completed,
        'amount' => 1_000,
        'initiated_by_user_id' => $user->id,
        'destination_wallet_id' => walletOf($user)->id,
        'idempotency_key' => (string) Str::uuid7(),
    ]);

    expect($transaction->id)->not->toBe('00000000-0000-7000-8000-000000000000')
        ->and($transaction->id)->toMatch(UUID_V7);
});

test('não deixa o formulário de depósito escolher carteira, dono ou situação', function () {
    $ana = userWithWallet();
    $bia = userWithWallet();

    $this->actingAs($ana);

    postDeposito([
        'wallet_id' => walletOf($bia)->id,
        'destination_wallet_id' => walletOf($bia)->id,
        'initiated_by_user_id' => $bia->id,
        'status' => 'reversed',
        'balance' => 500_000,
    ])->assertRedirect(route('dashboard'));

    $transaction = Transaction::query()->sole();

    expect($transaction->destination_wallet_id)->toBe(walletOf($ana)->id)
        ->and($transaction->initiated_by_user_id)->toBe($ana->id)
        ->and($transaction->status)->toBe(TransactionStatus::Completed)
        ->and(walletOf($ana)->balance)->toBe(100)
        ->and(walletOf($bia)->balance)->toBe(0);
});

test('não deixa o formulário de transferência escolher a carteira de origem', function () {
    $ana = userWithWallet(10_000);
    $bia = userWithWallet(10_000);
    $carlos = userWithWallet();

    $this->actingAs($ana);

    $this->post(route('transfers.store'), [
        'recipient_email' => $carlos->email,
        'amount' => '10,00',
        'idempotency_key' => (string) Str::uuid7(),
        'source_wallet_id' => walletOf($bia)->id,
        'initiated_by_user_id' => $bia->id,
    ])->assertRedirect(route('dashboard'));

    // O dinheiro saiu de quem enviou, nao da carteira indicada no formulario.
    expect(walletOf($ana)->balance)->toBe(9_000)
        ->and(walletOf($bia)->balance)->toBe(10_000)
        ->and(walletOf($carlos)->balance)->toBe(1_000);
});

test('não deixa o cadastro definir campos que não são dele', function () {
    $this->post(route('register'), [
        'name' => 'Ana',
        'email' => 'ana@example.test',
        'password' => 'senha-bem-segura',
        'password_confirmation' => 'senha-bem-segura',
        'email_verified_at' => now()->toDateTimeString(),
        'remember_token' => 'token-escolhido-por-fora',
    ])->assertRedirect(route('dashboard'));

    $user = User::query()->sole();

    expect($user->email_verified_at)->toBeNull()
        ->and($user->remember_token)->not->toBe('token-escolhido-por-fora')
        ->and($user->password)->not->toBe('senha-bem-segura');
});
