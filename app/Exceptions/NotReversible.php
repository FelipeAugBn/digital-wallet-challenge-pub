<?php

namespace App\Exceptions;

use Exception;

/**
 * A operacao nao e de um tipo que se desfaz.
 *
 * Na pratica isso significa um estorno: desfazer um estorno recriaria o dinheiro
 * que ele tirou, e o historico deixaria de contar uma historia unica.
 */
class NotReversible extends Exception
{
    /** Guarda a mensagem unica que a tela mostra na recusa. */
    public function __construct()
    {
        parent::__construct('Esta operação não pode ser estornada.');
    }
}
