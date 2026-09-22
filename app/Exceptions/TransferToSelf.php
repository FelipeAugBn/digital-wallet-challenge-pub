<?php

namespace App\Exceptions;

use Exception;

/**
 * O e-mail informado e o da propria pessoa.
 *
 * A recusa vem antes de qualquer lock: transferir para si mesmo bloquearia a
 * mesma carteira duas vezes e nao moveria dinheiro nenhum.
 */
class TransferToSelf extends Exception
{
    /** Guarda a mensagem unica que o controller mostra no campo de e-mail. */
    public function __construct()
    {
        parent::__construct('Você não pode transferir para a sua própria carteira.');
    }
}
