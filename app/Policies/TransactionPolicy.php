<?php

namespace App\Policies;

use App\Models\Transaction;
use App\Models\User;
use App\Support\ReversalEligibility;

/**
 * Quem pode agir sobre uma operacao financeira.
 *
 * A Policy responde pelo que nao muda depois que a operacao nasce: o tipo dela
 * e de quem ela e. O status fica de fora de proposito. Ele muda, e uma resposta
 * baseada nele valeria apenas no instante da leitura: entre desenhar o botao e
 * receber o POST, a mesma operacao pode ter sido estornada em outra aba. Quem
 * decide sobre o status e a Action, com a linha bloqueada.
 */
class TransactionPolicy
{
    /** So quem iniciou um deposito ou uma transferencia pode pedir o estorno. */
    public function reverse(User $user, Transaction $transaction): bool
    {
        return ReversalEligibility::typeIsReversible($transaction)
            && ReversalEligibility::wasInitiatedBy($transaction, $user);
    }
}
