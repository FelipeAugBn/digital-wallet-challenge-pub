<?php

namespace App\Enums;

/**
 * O que a operacao faz com o dinheiro.
 *
 * Os valores espelham o CHECK `transactions_type_valid`: mudar um lado sem o
 * outro faz o PostgreSQL recusar a insercao.
 */
enum TransactionType: string
{
    case Deposit = 'deposit';
    case Transfer = 'transfer';
    case Reversal = 'reversal';
}
