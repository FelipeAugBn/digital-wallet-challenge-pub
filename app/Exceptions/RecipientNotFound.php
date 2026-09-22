<?php

namespace App\Exceptions;

use Exception;

/**
 * O e-mail digitado tem formato valido, mas ninguem aqui usa esse endereco.
 *
 * A mensagem nao confirma nem nega cadastro de terceiros com mais detalhe do
 * que o necessario, e nao cita tabela, coluna nem identificador interno.
 */
class RecipientNotFound extends Exception
{
    /** Guarda a mensagem unica que o controller mostra no campo de e-mail. */
    public function __construct()
    {
        parent::__construct('Não encontramos ninguém com esse e-mail.');
    }
}
