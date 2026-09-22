<?php

use App\Enums\TransactionType;
use App\Exceptions\IdempotencyConflict;
use App\Exceptions\InsufficientFunds;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Models\WalletEntry;
use Illuminate\Support\Str;

require_once __DIR__.'/helpers.php';

test('devolve a transferência original quando o mesmo pedido chega duas vezes', function () {
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

test('percorre a violação de unicidade real quando a chave já está gravada', function () {
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

test('responde à repetição pela web com o mesmo redirect e a mesma mensagem', function () {
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

test('recusa a mesma chave quando só o valor é diferente', function () {
    $ana = userWithWallet(5_000);
    $bia = userWithWallet();
    $key = (string) Str::uuid7();

    transfer($ana, $bia, '30,00', $key);

    expect(fn () => transfer($ana, $bia, '20,00', $key))->toThrow(IdempotencyConflict::class);

    expect(Transaction::count())->toBe(1)
        ->and(WalletEntry::count())->toBe(2)
        ->and(walletOf($ana)->balance)->toBe(2_000);
});

test('recusa a mesma chave quando só o destinatário é diferente', function () {
    $ana = userWithWallet(5_000);
    $bia = userWithWallet();
    $carol = userWithWallet();
    $key = (string) Str::uuid7();

    transfer($ana, $bia, '30,00', $key);

    expect(fn () => transfer($ana, $carol, '30,00', $key))->toThrow(IdempotencyConflict::class);

    expect(Transaction::count())->toBe(1)
        ->and(walletOf($carol)->balance)->toBe(0);
});

test('recusa a mesma chave quando só o tipo é diferente', function () {
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

test('permite que dois remetentes usem exatamente a mesma chave', function () {
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

test('libera a chave de novo quando a tentativa sofreu rollback por falta de saldo', function () {
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
