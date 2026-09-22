<?php

use App\Models\User;
use App\Models\Wallet;

test('cria a carteira com saldo zero', function () {
    $wallet = Wallet::create(['user_id' => User::factory()->create()->id]);

    expect($wallet->fresh()->balance)->toBe(0);
});

test('ignora saldo enviado por atribuição em massa', function () {
    expect((new Wallet)->getFillable())->not->toContain('balance');

    $wallet = Wallet::create([
        'user_id' => User::factory()->create()->id,
        'balance' => 100_000,
    ]);

    expect($wallet->fresh()->balance)->toBe(0);
});

test('navega do usuário para a carteira', function () {
    $user = User::factory()->create();
    $wallet = Wallet::create(['user_id' => $user->id]);

    expect($user->wallet)->not->toBeNull()
        ->and($user->wallet->id)->toBe($wallet->id);
});

test('navega da carteira para o usuário', function () {
    $user = User::factory()->create();
    $wallet = Wallet::create(['user_id' => $user->id]);

    expect($wallet->user)->not->toBeNull()
        ->and($wallet->user->id)->toBe($user->id);
});
