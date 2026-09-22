<?php

namespace App\Enums;

/**
 * A origem do estorno, registrada porque os dois gatilhos sao diferentes.
 *
 * `user_request` parte de quem iniciou a operacao; `inconsistency` parte de um
 * operador pelo comando de linha e pode nao ter autor. Espelha o CHECK
 * `transactions_reversal_reason_valid`.
 */
enum ReversalReason: string
{
    case UserRequest = 'user_request';
    case Inconsistency = 'inconsistency';
}
