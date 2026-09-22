<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * A mesma chave voltou com dados diferentes dos que ja foram gravados.
 *
 * Nao e replay: e um formulario velho reenviado depois de alguma edicao. A
 * mensagem fala do que a pessoa pode fazer e nao cita banco nem constraint.
 *
 * `ShouldntReport` porque isto nao e falha: e uma recusa prevista, que a Action
 * ja registra como aviso com o motivo e os identificadores. Sem essa marca o
 * `report()` do Laravel corre antes do `render()` e a mesma recusa aparece uma
 * segunda vez como erro — a linha de aviso diria "recusado" e a de erro diria
 * "quebrou", sobre o mesmo instante. Isso vale so para esta excecao: nada aqui
 * muda o tratamento de qualquer falha inesperada.
 */
class IdempotencyConflict extends Exception implements ShouldntReport
{
    /** Guarda a mensagem unica para os dois pontos que a usam. */
    public function __construct()
    {
        parent::__construct('Esta operação já foi registrada com outros dados. Confira os dados e envie novamente.');
    }

    /**
     * Volta para o formulario com o erro no formato que as telas ja exibem.
     *
     * A chave antiga fica de fora do input preservado de proposito: ela ja esta
     * gasta, entao a tela precisa nascer com uma nova para a pessoa seguir.
     */
    public function render(Request $request): RedirectResponse
    {
        return back()
            ->withInput($request->except('idempotency_key'))
            ->withErrors(['amount' => $this->getMessage()]);
    }
}
