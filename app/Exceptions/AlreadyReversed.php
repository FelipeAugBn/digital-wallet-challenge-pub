<?php

namespace App\Exceptions;

use Exception;

/**
 * A operacao ja foi desfeita antes desta tentativa.
 *
 * A recusa nasce do status relido com a linha bloqueada, nao de uma violacao de
 * unicidade: uma segunda tentativa simultanea espera o lock, le `reversed` e
 * recebe esta mensagem em vez de um erro de banco.
 */
class AlreadyReversed extends Exception
{
    /** Guarda a mensagem unica que a tela mostra na segunda tentativa. */
    public function __construct()
    {
        parent::__construct('Esta operação já foi estornada.');
    }
}
