<?php

namespace App\Console\Commands;

use App\Actions\ReverseTransaction;
use App\Enums\ReversalReason;
use App\Exceptions\AlreadyReversed;
use App\Exceptions\NotReversible;
use App\Exceptions\TransactionNotFound;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ReverseTransactionCommand extends Command
{
    /** O motivo e opcao, e nao valor fixo, para que a recusa dos outros seja testavel. */
    protected $signature = 'wallet:reverse
                            {transaction : Identificador (UUID) da operação a estornar}
                            {--reason= : Motivo do estorno; somente "inconsistency" é aceito}';

    protected $description = 'Estorna uma operação por inconsistência usando a mesma Action da aplicação';

    /**
     * Adapta a linha de comando para a Action, e nada alem disso.
     *
     * Conferir tipo, status ou saldo aqui seria uma segunda versao da regra,
     * decidindo com dados lidos fora do lock. Sem Policy de proposito: quem
     * roda o comando ja tem o shell do container, e a fronteira que sobra e o
     * motivo, que aceita so `inconsistency`.
     */
    public function handle(ReverseTransaction $reverseTransaction): int
    {
        // Comparacao estrita de proposito: `user_request`, `INCONSISTENCY` e a
        // ausencia da opcao caem todos aqui, sem normalizacao que os aproxime.
        if ($this->option('reason') !== ReversalReason::Inconsistency->value) {
            $this->error('Informe --reason=inconsistency. Esse é o único motivo aceito por aqui.');
            $this->line('O estorno pedido por uma pessoa acontece somente pela aplicação.');

            return self::INVALID;
        }

        $transactionId = $this->argument('transaction');

        // O formato e conferido antes de qualquer consulta: um identificador
        // torto chegaria ao PostgreSQL como erro de conversao de tipo, e a
        // pessoa receberia uma mensagem de banco em vez de uma instrucao.
        if (! Str::isUuid($transactionId)) {
            $this->error('Informe o identificador da operação no formato UUID.');

            return self::INVALID;
        }

        try {
            $reversal = $reverseTransaction->handle($transactionId, ReversalReason::Inconsistency, null);
        } catch (TransactionNotFound|NotReversible|AlreadyReversed $recusa) {
            // As tres recusas previstas viram a mesma mensagem que a tela
            // mostra. `ReversalNotAllowed` nao entra na lista porque ela so
            // nasce do motivo `user_request`: se aparecesse aqui, seria sinal
            // de outra coisa quebrada e precisa subir em vez de virar aviso.
            $this->error($recusa->getMessage());

            return self::FAILURE;
        }

        $this->info('Operação '.$transactionId.' estornada por inconsistência.');
        $this->line('Estorno registrado como '.$reversal->id.'.');

        return self::SUCCESS;
    }
}
