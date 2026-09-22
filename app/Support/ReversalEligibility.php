<?php

namespace App\Support;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;

/**
 * As regras que dizem se uma operacao pode ser estornada.
 *
 * Ficam aqui porque tres lugares precisam das mesmas respostas e nenhum deles
 * pode responder por conta propria: a Policy decide quem ve o botao e quem pode
 * postar, o extrato decide se desenha o botao e a Action decide se grava o
 * estorno. Sao funcoes puras, sem consulta e sem efeito, entao cada uma cobra
 * so o dado que ja esta na mao de quem pergunta.
 *
 * A separacao entre elas nao e cosmetica: tipo e autoria nunca mudam depois que
 * a operacao nasce, e por isso podem ser conferidos a qualquer momento; o
 * status muda, e por isso so vale a leitura feita sob lock dentro da Action.
 */
final class ReversalEligibility
{
    /** Depositos e transferencias se desfazem; um estorno nunca se desfaz. */
    public static function typeIsReversible(Transaction $transaction): bool
    {
        return in_array(
            $transaction->type,
            [TransactionType::Deposit, TransactionType::Transfer],
            true,
        );
    }

    /**
     * Se a pessoa e quem pediu a operacao.
     *
     * O estorno automatico nao tem autor, entao uma operacao sem
     * `initiated_by_user_id` nao pertence a ninguem.
     */
    public static function wasInitiatedBy(Transaction $transaction, User $user): bool
    {
        return $transaction->initiated_by_user_id !== null
            && (int) $transaction->initiated_by_user_id === $user->id;
    }

    /** O unico criterio que muda com o tempo; so vale lido sob lock. */
    public static function isCompleted(Transaction $transaction): bool
    {
        return $transaction->status === TransactionStatus::Completed;
    }
}
