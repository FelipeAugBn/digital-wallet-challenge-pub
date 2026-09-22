<?php

namespace App\Http\Controllers;

use App\Actions\ReverseTransaction;
use App\Enums\ReversalReason;
use App\Exceptions\AlreadyReversed;
use App\Exceptions\NotReversible;
use App\Exceptions\ReversalNotAllowed;
use App\Exceptions\TransactionNotFound;
use App\Models\Transaction;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ReversalController extends Controller
{
    /**
     * Estorna uma operacao a pedido de quem a iniciou.
     *
     * O controller nao decide nada sozinho: a Policy diz se esta pessoa pode
     * pedir o estorno desta operacao e a Action diz se a operacao ainda pode ser
     * estornada. Aqui so se autoriza, delega e redireciona, para que atualizar a
     * pagina depois do estorno nao repita o POST.
     *
     * As recusas esperadas viram mensagem de tela. Nenhuma delas cita banco,
     * lock, constraint ou nome de classe, e qualquer falha inesperada continua
     * subindo em vez de virar um aviso tranquilizador.
     */
    public function __invoke(Request $request, Transaction $transaction, ReverseTransaction $reverseTransaction): RedirectResponse
    {
        Gate::authorize('reverse', $transaction);

        $destino = back(fallback: route('statement'));

        try {
            $reverseTransaction->handle(
                $transaction->id,
                ReversalReason::UserRequest,
                $request->user(),
            );
        } catch (TransactionNotFound|NotReversible|AlreadyReversed|ReversalNotAllowed $recusa) {
            return $destino->withErrors(['estorno' => $recusa->getMessage()]);
        }

        return $destino->with(
            'sucesso',
            'Estorno de '.Money::fromCents($transaction->amount)->format().' realizado.',
        );
    }
}
