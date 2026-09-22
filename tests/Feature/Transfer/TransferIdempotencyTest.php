<?php

use App\Enums\TransactionType;
use App\Exceptions\IdempotencyConflict;
use App\Exceptions\InsufficientFunds;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Models\WalletEntry;
use Illuminate\Support\Str;

require_once __DIR__.'/helpers.php';

it('gives back the original transfer when the same request arrives twice', function () {
    $ana = userWithWallet(5_000);
    $bia = userWithWallet();
    $key = (string) Str::uuid7();

    $primeira = transfer($ana, $bia, '30,00', $key);
    $segunda = transfer($ana, $bia, '30,00', $key);

    expect($segunda->id)->toBe($primeira->id)
        ->and(Transaction::count())->toBe(1)
        ->and(WalletEntry::count())->toBe(2)
        ->and(walletOf($ana)->balance)->toBe(2_000)
        ->and(walletOf($bia)->balance)->toBe(3_000);
});

it('walks the real unique violation when the key is already persisted', function () {
    $ana = userWithWallet(5_000);
    $bia = userWithWallet();
    $key = (string) Str::uuid7();

    // Gravada por fora, com exatamente os mesmos dados: a Action nao tem como
    // saber, entao a descoberta so pode vir da recusa do banco.
    $original = persistedTransaction($ana, $key, [
        'type' => TransactionType::Transfer,
        'amount' => 3_000,
        'source_wallet_id' => walletOf($ana)->id,
        'destination_wallet_id' => walletOf($bia)->id,
    ]);

    $devolvida = transfer($ana, $bia, '30,00', $key);

    expect($devolvida->id)->toBe($original->id)
        ->and(Transaction::count())->toBe(1)
        ->and(WalletEntry::count())->toBe(0)
        ->and(walletOf($ana)->balance)->toBe(5_000)
        ->and(walletOf($bia)->balance)->toBe(0);
});

it('answers a replay through the web with the same redirect and message', function () {
    $ana = userWithWallet(5_000);
    $bia = userWithWallet();
    $payload = [
        'recipient_email' => $bia->email,
        'amount' => '30,00',
        'idempotency_key' => (string) Str::uuid7(),
    ];

    $this->actingAs($ana)->post(route('transfers.store'), $payload)->assertRedirect(route('dashboard'));

    $this->actingAs($ana)
        ->post(route('transfers.store'), $payload)
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('sucesso', 'Transferência de R$ 30,00 para '.$bia->email.' realizada.');

    expect(Transaction::count())->toBe(1)
        ->and(WalletEntry::count())->toBe(2)
        ->and(walletOf($ana)->balance)->toBe(2_000)
        ->and(walletOf($bia)->balance)->toBe(3_000);
});

it('refuses the same key when only the amount is different', function () {
    $ana = userWithWallet(5_000);
    $bia = userWithWallet();
    $key = (string) Str::uuid7();

    transfer($ana, $bia, '30,00', $key);

    expect(fn () => transfer($ana, $bia, '20,00', $key))->toThrow(IdempotencyConflict::class);

    expect(Transaction::count())->toBe(1)
        ->and(WalletEntry::count())->toBe(2)
        ->and(walletOf($ana)->balance)->toBe(2_000);
});

it('refuses the same key when only the recipient is different', function () {
    $ana = userWithWallet(5_000);
    $bia = userWithWallet();
    $carol = userWithWallet();
    $key = (string) Str::uuid7();

    transfer($ana, $bia, '30,00', $key);

    expect(fn () => transfer($ana, $carol, '30,00', $key))->toThrow(IdempotencyConflict::class);

    expect(Transaction::count())->toBe(1)
        ->and(walletOf($carol)->balance)->toBe(0);
});

it('refuses the same key when only the type is different', function () {
    $ana = userWithWallet(5_000);
    $bia = userWithWallet();
    $key = (string) Str::uuid7();

    // Mesmo valor e mesmo destino de proposito: o unico dado diferente e o
    // tipo, entao e ele que precisa reprovar a repeticao.
    persistedTransaction($ana, $key, [
        'type' => TransactionType::Deposit,
        'amount' => 3_000,
        'destination_wallet_id' => walletOf($bia)->id,
    ]);

    expect(fn () => transfer($ana, $bia, '30,00', $key))->toThrow(IdempotencyConflict::class);

    expect(Transaction::count())->toBe(1)
        ->and(WalletEntry::count())->toBe(0)
        ->and(walletOf($ana)->balance)->toBe(5_000);
});

it('lets two senders use the very same key', function () {
    $key = (string) Str::uuid7();
    $ana = userWithWallet(5_000);
    $bia = userWithWallet(5_000);
    $carol = userWithWallet();

    $daAna = transfer($ana, $carol, '30,00', $key);
    $daBia = transfer($bia, $carol, '10,00', $key);

    expect($daAna->id)->not->toBe($daBia->id)
        ->and(Transaction::count())->toBe(2)
        ->and(walletOf($ana)->balance)->toBe(2_000)
        ->and(walletOf($bia)->balance)->toBe(4_000)
        ->and(walletOf($carol)->balance)->toBe(4_000);
});

it('frees the key again when the attempt was rolled back for lack of balance', function () {
    $ana = userWithWallet(1_000);
    $bia = userWithWallet();
    $key = (string) Str::uuid7();

    expect(fn () => transfer($ana, $bia, '30,00', $key))->toThrow(InsufficientFunds::class);

    // O insert da transacao voltou atras junto com o resto, entao a chave nao
    // ficou gasta e a mesma tentativa pode seguir depois que houver saldo.
    expect(Transaction::count())->toBe(0);

    Wallet::query()->whereKey(walletOf($ana)->id)->update(['balance' => 5_000]);

    $depois = transfer($ana, $bia, '30,00', $key);

    expect($depois->idempotency_key)->toBe($key)
        ->and(Transaction::count())->toBe(1)
        ->and(walletOf($ana)->balance)->toBe(2_000)
        ->and(walletOf($bia)->balance)->toBe(3_000);
});
