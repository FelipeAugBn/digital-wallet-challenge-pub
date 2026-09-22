<?php

use App\Models\User;
use App\Models\Wallet;

it('creates a wallet with a zero balance', function () {
    $wallet = Wallet::create(['user_id' => User::factory()->create()->id]);

    expect($wallet->fresh()->balance)->toBe(0);
});

it('ignores a balance sent through mass assignment', function () {
    expect((new Wallet)->getFillable())->not->toContain('balance');

    $wallet = Wallet::create([
        'user_id' => User::factory()->create()->id,
        'balance' => 100_000,
    ]);

    expect($wallet->fresh()->balance)->toBe(0);
});

it('navigates from the user to the wallet', function () {
    $user = User::factory()->create();
    $wallet = Wallet::create(['user_id' => $user->id]);

    expect($user->wallet)->not->toBeNull()
        ->and($user->wallet->id)->toBe($wallet->id);
});

it('navigates from the wallet to the user', function () {
    $user = User::factory()->create();
    $wallet = Wallet::create(['user_id' => $user->id]);

    expect($wallet->user)->not->toBeNull()
        ->and($wallet->user->id)->toBe($user->id);
});
