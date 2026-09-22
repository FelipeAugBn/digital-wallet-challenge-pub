<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * A mesma chave voltou com dados diferentes dos que ja foram gravados.
 *
 * Nao e replay: e um formulario velho reenviado depois de alguma edicao. A
 * mensagem fala do que a pessoa pode fazer e nao cita banco nem constraint.
 */
class IdempotencyConflict extends Exception
{
    /** Guarda a mensagem unica para os dois pontos que a usam. */
    public function __construct()
    {
        parent::__construct('Esta operação já foi registrada com outros dados. Confira o valor e envie novamente.');
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
