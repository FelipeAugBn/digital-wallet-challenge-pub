<?php

namespace App\Enums;

/**
 * Situacao da operacao ja concluida.
 *
 * Nasce `completed` e so vira `reversed` quando um estorno a anula; o valor
 * original nunca e alterado. Espelha o CHECK `transactions_status_valid`.
 */
enum TransactionStatus: string
{
    case Completed = 'completed';
    case Reversed = 'reversed';
}
