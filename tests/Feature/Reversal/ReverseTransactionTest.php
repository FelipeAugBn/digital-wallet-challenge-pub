<?php

use App\Enums\ReversalReason;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\WalletEntryType;
use App\Exceptions\AlreadyReversed;
use App\Exceptions\NotReversible;
use App\Exceptions\ReversalNotAllowed;
use App\Exceptions\TransactionNotFound;
use App\Models\Transaction;
use App\Models\WalletEntry;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

require_once __DIR__.'/helpers.php';

test('estorna o depósito com um débito na carteira que recebeu', function () {
    $ana = userWithWallet();
    $deposito = deposit($ana, '10,00');

    reverse($deposito, $ana);

    $debito = WalletEntry::query()->where('type', WalletEntryType::Debit)->sole();

    expect(walletOf($ana)->balance)->toBe(0)
        ->and($debito->wallet_id)->toBe(walletOf($ana)->id)
        ->and($debito->amount)->toBe(1_000)
        ->and($debito->balance_after)->toBe(0)
        ->and(WalletEntry::count())->toBe(2);
});

test('deixa o saldo negativo quando o valor do depósito já saiu', function () {
    $ana = userWithWallet();
    $bia = userWithWallet();
    $deposito = deposit($ana, '10,00');

    transfer($ana, $bia, '10,00');

    // A Ana está zerada: devolver o depósito só é possível deixando a carteira
    // negativa, e é justamente isso que a SPEC manda fazer.
    reverse($deposito, $ana);

    expect(walletOf($ana)->balance)->toBe(-1_000);
});

test('devolve o valor à origem e retira do destino na transferência', function () {
    $ana = userWithWallet(5_000);
    $bia = userWithWallet();

    reverse(transfer($ana, $bia, '30,00'), $ana);

    expect(walletOf($ana)->balance)->toBe(5_000)
        ->and(walletOf($bia)->balance)->toBe(0);
});

test('grava o balance_after de cada lançamento inverso', function () {
    $ana = userWithWallet(5_000);
    $bia = userWithWallet(2_000);

    $estorno = reverse(transfer($ana, $bia, '30,00'), $ana);

    $lancamentos = WalletEntry::query()
        ->where('transaction_id', $estorno->id)
        ->get()
        ->keyBy(fn (WalletEntry $entry) => $entry->type->value);

    expect($lancamentos)->toHaveCount(2)
        ->and($lancamentos['credit']->wallet_id)->toBe(walletOf($ana)->id)
        ->and($lancamentos['credit']->balance_after)->toBe(5_000)
        ->and($lancamentos['debit']->wallet_id)->toBe(walletOf($bia)->id)
        ->and($lancamentos['debit']->balance_after)->toBe(2_000)
        ->and($lancamentos['credit']->balance_after)->toBe(walletOf($ana)->balance)
        ->and($lancamentos['debit']->balance_after)->toBe(walletOf($bia)->balance);
});

test('mantém a operação original no banco, com o mesmo valor e marcada como estornada', function () {
    $ana = userWithWallet(5_000);
    $bia = userWithWallet();
    $transferencia = transfer($ana, $bia, '30,00');

    reverse($transferencia, $ana);

    $original = Transaction::query()->whereKey($transferencia->id)->sole();

    expect($original->status)->toBe(TransactionStatus::Reversed)
        ->and($original->amount)->toBe(3_000)
        ->and($original->type)->toBe(TransactionType::Transfer)
        ->and($original->source_wallet_id)->toBe(walletOf($ana)->id)
        ->and($original->destination_wallet_id)->toBe(walletOf($bia)->id)
        // Os lançamentos da operação original continuam onde estavam.
        ->and(WalletEntry::query()->where('transaction_id', $original->id)->count())->toBe(2);
});

test('grava tipo, motivo e vínculo na transação de estorno', function () {
    $ana = userWithWallet(5_000);
    $bia = userWithWallet();
    $transferencia = transfer($ana, $bia, '30,00');

    $estorno = reverse($transferencia, $ana);

    expect($estorno->type)->toBe(TransactionType::Reversal)
        ->and($estorno->status)->toBe(TransactionStatus::Completed)
        ->and($estorno->reversal_reason)->toBe(ReversalReason::UserRequest)
        ->and($estorno->original_transaction_id)->toBe($transferencia->id)
        ->and($estorno->amount)->toBe($transferencia->amount)
        ->and($estorno->initiated_by_user_id)->toBe($ana->id)
        // O caminho de volta: sai de quem recebeu, entra em quem pagou.
        ->and($estorno->source_wallet_id)->toBe(walletOf($bia)->id)
        ->and($estorno->destination_wallet_id)->toBe(walletOf($ana)->id)
        // Estorno não usa chave de idempotência.
        ->and($estorno->idempotency_key)->toBeNull();
});

test('deixa o destino vazio no estorno de um depósito', function () {
    $ana = userWithWallet();

    $estorno = reverse(deposit($ana, '10,00'), $ana);

    expect($estorno->source_wallet_id)->toBe(walletOf($ana)->id)
        ->and($estorno->destination_wallet_id)->toBeNull()
        ->and(WalletEntry::query()->where('transaction_id', $estorno->id)->count())->toBe(1);
});

test('desfaz transação, lançamentos e saldos quando o último passo falha', function () {
    $ana = userWithWallet(5_000);
    $bia = userWithWallet();
    $transferencia = transfer($ana, $bia, '30,00');

    // O último passo do estorno é marcar a original: é a única atualização de
    // `transactions` da operação, então derrubá-la derruba exatamente o fim.
    Event::listen('eloquent.updating: '.Transaction::class, function () {
        throw new RuntimeException('falha ao marcar a operacao original');
    });

    expect(fn () => reverse($transferencia, $ana))->toThrow(RuntimeException::class);

    expect(Transaction::count())->toBe(1)
        ->and(Transaction::sole()->status)->toBe(TransactionStatus::Completed)
        ->and(WalletEntry::count())->toBe(2)
        ->and(walletOf($ana)->balance)->toBe(2_000)
        ->and(walletOf($bia)->balance)->toBe(3_000);
});

test('recusa a segunda tentativa sem criar outro estorno', function () {
    $ana = userWithWallet(5_000);
    $bia = userWithWallet();
    $transferencia = transfer($ana, $bia, '30,00');

    reverse($transferencia, $ana);

    expect(fn () => reverse($transferencia, $ana))
        ->toThrow(AlreadyReversed::class, 'Esta operação já foi estornada.');

    expect(Transaction::query()->where('type', TransactionType::Reversal)->count())->toBe(1)
        ->and(walletOf($ana)->balance)->toBe(5_000)
        ->and(walletOf($bia)->balance)->toBe(0);
});

test('recusa o estorno de um estorno', function () {
    $ana = userWithWallet();
    $estorno = reverse(deposit($ana, '10,00'), $ana);

    expect(fn () => reverse($estorno, $ana))
        ->toThrow(NotReversible::class, 'Esta operação não pode ser estornada.');

    expect(Transaction::count())->toBe(2)
        ->and(walletOf($ana)->balance)->toBe(0);
});

test('recusa a solicitação de quem não iniciou a operação', function () {
    $ana = userWithWallet(5_000);
    $bia = userWithWallet();
    $transferencia = transfer($ana, $bia, '30,00');

    // A Bia recebeu, mas quem iniciou foi a Ana.
    expect(fn () => reverse($transferencia, $bia))->toThrow(ReversalNotAllowed::class);

    expect(Transaction::count())->toBe(1)
        ->and(Transaction::sole()->status)->toBe(TransactionStatus::Completed)
        ->and(walletOf($bia)->balance)->toBe(3_000);
});

test('recusa a solicitação de usuário que chega sem pessoa iniciadora', function () {
    $ana = userWithWallet();
    $deposito = deposit($ana, '10,00');

    // Sem autor não há como saber se quem pediu foi quem iniciou, e este motivo
    // exige essa resposta; o estorno sem autor tem outro motivo.
    expect(fn () => reverse($deposito, null))->toThrow(ReversalNotAllowed::class);

    expect(Transaction::count())->toBe(1)
        ->and(walletOf($ana)->balance)->toBe(1_000);
});

test('recusa uma operação que não existe', function () {
    $ana = userWithWallet();
    deposit($ana, '10,00');

    $inexistente = new Transaction;
    $inexistente->id = (string) Str::uuid7();
    $inexistente->initiated_by_user_id = $ana->id;

    expect(fn () => reverse($inexistente, $ana))->toThrow(TransactionNotFound::class);

    expect(Transaction::count())->toBe(1);
});

test('aceita o motivo de inconsistência sem pessoa iniciadora', function () {
    // O gatilho operacional ainda não existe; o que este teste protege é a
    // assinatura que ele vai usar, para que ela não seja quebrada sem aviso.
    $ana = userWithWallet();
    $deposito = deposit($ana, '10,00');

    $estorno = reverse($deposito, null, ReversalReason::Inconsistency);

    expect($estorno->initiated_by_user_id)->toBeNull()
        ->and($estorno->reversal_reason)->toBe(ReversalReason::Inconsistency)
        ->and(walletOf($ana)->balance)->toBe(0);
});
