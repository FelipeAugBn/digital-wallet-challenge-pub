<?php

namespace App\Exceptions;

use Exception;

/**
 * Quem pediu o estorno nao foi quem iniciou a operacao.
 *
 * A tela ja barra esse caminho pela Policy; a Action confere de novo porque ela
 * tambem sera chamada fora do navegador e nao pode depender de quem a chamou.
 */
class ReversalNotAllowed extends Exception
{
    /** Guarda a mensagem unica que a tela mostra na recusa. */
    public function __construct()
    {
        parent::__construct('Você só pode estornar uma operação que iniciou.');
    }
}
