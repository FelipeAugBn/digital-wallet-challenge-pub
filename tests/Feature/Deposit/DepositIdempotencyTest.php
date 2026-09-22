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

test('devolve a transação original quando o mesmo pedido chega duas vezes', function () {
    $user = userWithWallet();
    $key = (string) Str::uuid7();

    $primeira = deposit($user, '10,00', $key);
    $segunda = deposit($user, '10,00', $key);

    expect($segunda->id)->toBe($primeira->id)
        ->and(Transaction::count())->toBe(1)
        ->and(WalletEntry::count())->toBe(1)
        ->and(walletOf($user)->balance)->toBe(1_000);
});

test('percorre a violação de unicidade real quando a chave já está gravada', function () {
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

test('responde à repetição pela web com o mesmo redirect e a mesma mensagem', function () {
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

test('recusa a mesma chave com valor diferente', function () {
    $user = userWithWallet();
    $key = (string) Str::uuid7();

    deposit($user, '10,00', $key);

    expect(fn () => deposit($user, '20,00', $key))->toThrow(IdempotencyConflict::class);

    expect(Transaction::count())->toBe(1)
        ->and(walletOf($user)->balance)->toBe(1_000);
});

test('recusa a mesma chave quando a operação gravada é de outro tipo', function () {
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

test('recusa a mesma chave quando o destino gravado é outra carteira', function () {
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

test('mostra o conflito na tela sem vazar detalhe do banco', function () {
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

test('permite que duas pessoas usem exatamente a mesma chave', function () {
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

test('relança violação de unicidade vinda de outra constraint', function () {
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

test('relança quando o conflito de chave não tem nada gravado para essa pessoa', function () {
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
