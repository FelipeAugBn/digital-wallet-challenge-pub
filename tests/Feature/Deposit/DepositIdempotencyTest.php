<?php

use App\Enums\TransactionType;
use App\Exceptions\IdempotencyConflict;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Models\WalletEntry;
use App\Support\IdempotentTransaction;
use App\Support\Money;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require_once __DIR__.'/helpers.php';

it('gives back the original transaction when the same request arrives twice', function () {
    $user = userWithWallet();
    $key = (string) Str::uuid7();

    $primeira = deposit($user, '10,00', $key);
    $segunda = deposit($user, '10,00', $key);

    expect($segunda->id)->toBe($primeira->id)
        ->and(Transaction::count())->toBe(1)
        ->and(WalletEntry::count())->toBe(1)
        ->and(walletOf($user)->balance)->toBe(1_000);
});

it('walks the real unique violation when the key is already persisted', function () {
    $user = userWithWallet();
    $key = (string) Str::uuid7();

    // Gravada por fora: a Action nao tem como saber, entao a descoberta so
    // pode vir da recusa do banco na hora de inserir.
    $original = persistedTransaction($user, $key, ['amount' => 1_000]);

    $devolvida = deposit($user, '10,00', $key);

    expect($devolvida->id)->toBe($original->id)
        ->and(Transaction::count())->toBe(1)
        ->and(WalletEntry::count())->toBe(0)
        ->and(walletOf($user)->balance)->toBe(0);
});

it('answers a replay through the web with the same redirect and message', function () {
    $user = userWithWallet();
    $key = (string) Str::uuid7();
    $payload = ['amount' => '10,00', 'idempotency_key' => $key];

    $this->actingAs($user)->post(route('deposits.store'), $payload)->assertRedirect(route('dashboard'));

    $this->actingAs($user)
        ->post(route('deposits.store'), $payload)
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('sucesso', 'Depósito de R$ 10,00 realizado.');

    expect(Transaction::count())->toBe(1)
        ->and(walletOf($user)->balance)->toBe(1_000);
});

it('refuses the same key with a different amount', function () {
    $user = userWithWallet();
    $key = (string) Str::uuid7();

    deposit($user, '10,00', $key);

    expect(fn () => deposit($user, '20,00', $key))->toThrow(IdempotencyConflict::class);

    expect(Transaction::count())->toBe(1)
        ->and(walletOf($user)->balance)->toBe(1_000);
});

it('refuses the same key when the persisted operation is of another type', function () {
    $user = userWithWallet();
    $outro = userWithWallet();
    $key = (string) Str::uuid7();

    // Mesmo valor e mesmo destino de proposito: o unico dado diferente e o
    // tipo, entao e ele que precisa reprovar a repeticao.
    persistedTransaction($user, $key, [
        'type' => TransactionType::Transfer,
        'amount' => 1_000,
        'source_wallet_id' => walletOf($outro)->id,
        'destination_wallet_id' => walletOf($user)->id,
    ]);

    expect(fn () => deposit($user, '10,00', $key))->toThrow(IdempotencyConflict::class);

    expect(Transaction::count())->toBe(1);
});

it('refuses the same key when the persisted destination is another wallet', function () {
    $user = userWithWallet();
    $outro = userWithWallet();
    $key = (string) Str::uuid7();

    persistedTransaction($user, $key, [
        'amount' => 1_000,
        'destination_wallet_id' => walletOf($outro)->id,
    ]);

    expect(fn () => deposit($user, '10,00', $key))->toThrow(IdempotencyConflict::class);

    expect(Transaction::count())->toBe(1);
});

it('shows the conflict on screen without leaking any database detail', function () {
    $user = userWithWallet();
    $key = (string) Str::uuid7();

    deposit($user, '10,00', $key);

    $response = $this->actingAs($user)
        ->from(route('deposits.create'))
        ->post(route('deposits.store'), ['amount' => '20,00', 'idempotency_key' => $key]);

    $response->assertRedirect(route('deposits.create'))
        ->assertSessionHasErrors(['amount' => 'Esta operação já foi registrada com outros dados. Confira os dados e envie novamente.']);

    // A chave gasta nao volta: o formulario precisa nascer com uma nova.
    expect(session()->getOldInput('idempotency_key'))->toBeNull()
        ->and(session()->getOldInput('amount'))->toBe('20,00');

    $conteudo = $this->get(route('deposits.create'))->getContent();

    expect($conteudo)->not->toContain('SQLSTATE')
        ->not->toContain('transactions_user_idempotency_key_unique')
        ->not->toContain('value="'.$key.'"');
});

it('lets two people use the very same key', function () {
    $key = (string) Str::uuid7();
    $ana = userWithWallet();
    $bia = userWithWallet();

    $daAna = deposit($ana, '10,00', $key);
    $daBia = deposit($bia, '25,00', $key);

    expect($daAna->id)->not->toBe($daBia->id)
        ->and(Transaction::count())->toBe(2)
        ->and(walletOf($ana)->balance)->toBe(1_000)
        ->and(walletOf($bia)->balance)->toBe(2_500);
});

it('rethrows a unique violation that comes from another constraint', function () {
    $user = userWithWallet();
    $key = (string) Str::uuid7();

    // A chave ja esta gravada e bate em tudo: se o componente confundisse
    // qualquer 23505 com repeticao, ele devolveria esta operacao em silencio.
    persistedTransaction($user, $key, ['amount' => 1_000]);

    $chamada = fn () => app(IdempotentTransaction::class)->run(
        $user->id,
        $key,
        TransactionType::Deposit,
        Money::fromCents(1_000),
        walletOf($user)->id,
        function (): Transaction {
            // Segunda carteira para a mesma pessoa: viola `wallets_user_id_unique`,
            // que nao e o indice de idempotencia e portanto nao e repeticao.
            Wallet::create(['user_id' => Wallet::query()->value('user_id')]);

            return new Transaction;
        },
    );

    expect($chamada)->toThrow(QueryException::class);
});

it('rethrows when the key conflict has nothing persisted for this person', function () {
    $ana = userWithWallet();
    $bia = userWithWallet();
    $key = (string) Str::uuid7();

    persistedTransaction($bia, $key);

    // Uma violacao de verdade do indice de idempotencia, colhida da Bia.
    $violacaoReal = null;

    try {
        DB::transaction(fn () => persistedTransaction($bia, $key));
    } catch (QueryException $exception) {
        $violacaoReal = $exception;
    }

    expect($violacaoReal)->not->toBeNull();

    // A consulta de repeticao e feita pelo par pessoa mais chave, entao para a
    // Ana nao existe nada gravado e o erro original precisa continuar subindo.
    $chamada = fn () => app(IdempotentTransaction::class)->run(
        $ana->id,
        $key,
        TransactionType::Deposit,
        Money::fromCents(1_000),
        walletOf($ana)->id,
        fn () => throw $violacaoReal,
    );

    expect($chamada)->toThrow(QueryException::class);
});
