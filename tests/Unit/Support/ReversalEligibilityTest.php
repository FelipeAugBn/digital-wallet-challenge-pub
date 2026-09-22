<?php

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;
use App\Support\ReversalEligibility;

/** Uma operação em memória: estas regras não consultam banco nenhum. */
function operacao(
    TransactionType $type,
    TransactionStatus $status = TransactionStatus::Completed,
    ?int $autor = 7,
): Transaction {
    $transaction = new Transaction;
    $transaction->type = $type;
    $transaction->status = $status;
    $transaction->initiated_by_user_id = $autor;

    return $transaction;
}

/** Uma pessoa em memória, identificada apenas pelo id. */
function pessoa(int $id): User
{
    $user = new User;
    $user->id = $id;

    return $user;
}

test('aceita estornar depósito e transferência', function (TransactionType $type) {
    expect(ReversalEligibility::typeIsReversible(operacao($type)))->toBeTrue();
})->with([TransactionType::Deposit, TransactionType::Transfer]);

test('recusa estornar um estorno', function () {
    expect(ReversalEligibility::typeIsReversible(operacao(TransactionType::Reversal)))->toBeFalse();
});

test('reconhece quem iniciou a operação', function () {
    expect(ReversalEligibility::wasInitiatedBy(operacao(TransactionType::Deposit), pessoa(7)))->toBeTrue();
});

test('não reconhece quem não iniciou a operação', function () {
    expect(ReversalEligibility::wasInitiatedBy(operacao(TransactionType::Transfer), pessoa(8)))->toBeFalse();
});

test('não dá dono a uma operação sem autor', function () {
    // O estorno automático nasce sem autor; ninguém pode se apresentar como ele.
    $automatico = operacao(TransactionType::Reversal, autor: null);

    // O identificador vazio entra aqui de propósito: sem a conferência explícita
    // de nulo, converter a coluna ausente para inteiro daria zero, e zero é
    // exatamente o que uma comparação descuidada trataria como uma pessoa.
    expect(ReversalEligibility::wasInitiatedBy($automatico, pessoa(7)))->toBeFalse()
        ->and(ReversalEligibility::wasInitiatedBy($automatico, pessoa(0)))->toBeFalse();
});

test('considera concluída somente a operação com status completed', function () {
    expect(ReversalEligibility::isCompleted(operacao(TransactionType::Deposit)))->toBeTrue()
        ->and(ReversalEligibility::isCompleted(
            operacao(TransactionType::Deposit, TransactionStatus::Reversed)
        ))->toBeFalse();
});
