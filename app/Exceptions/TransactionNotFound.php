<?php

namespace App\Exceptions;

use Exception;

/**
 * O identificador recebido nao corresponde a nenhuma operacao.
 *
 * Pela tela isso quase nunca acontece, porque a rota resolve a transacao antes;
 * o caminho existe para quem chama a Action direto com um identificador que ja
 * nao vale. A mensagem nao repete o identificador nem cita tabela.
 */
class TransactionNotFound extends Exception
{
    /** Guarda a mensagem unica que os dois gatilhos de estorno mostram. */
    public function __construct()
    {
        parent::__construct('Não encontramos essa operação.');
    }
}
