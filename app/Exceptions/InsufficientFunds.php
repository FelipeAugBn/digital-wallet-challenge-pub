<?php

namespace App\Exceptions;

use Exception;

/**
 * A carteira de origem nao cobre o valor pedido.
 *
 * A conferencia acontece com o saldo lido da linha ja bloqueada, entao esta
 * recusa vale ate o commit e nao pode ser furada por uma operacao paralela.
 */
class InsufficientFunds extends Exception
{
    /** Guarda a mensagem unica que o controller mostra no campo de valor. */
    public function __construct()
    {
        parent::__construct('Saldo insuficiente para esta transferência.');
    }
}
