<?php

namespace App\Http\Controllers;

use App\Http\Requests\StatementFilterRequest;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WalletEntry;
use App\Support\Money;
use App\Support\ReversalEligibility;
use App\Support\StatementEntry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class StatementController extends Controller
{
    /** O tamanho de pagina definido na SPEC. */
    private const POR_PAGINA = 15;

    /**
     * O extrato da propria carteira, do mais recente para o mais antigo.
     *
     * A carteira sai de quem esta autenticado; nenhum identificador de carteira
     * e aceito pela URL. O desempate por `id` deixa a ordem estavel mesmo
     * quando dois lancamentos nascem no mesmo instante, e e isso que impede a
     * pagina dois de repetir ou pular um lancamento da pagina um.
     *
     * O periodo, quando vem, apenas estreita essa mesma consulta: ele nunca
     * abre nada que sem ele estivesse fechado.
     */
    public function __invoke(StatementFilterRequest $request): View
    {
        $user = $request->user();
        $wallet = $user->wallet()->firstOrFail();
        $filtros = $request->filtros();

        $pagina = WalletEntry::query()
            ->where('wallet_id', $wallet->id)
            ->when($request->inicio(), fn (Builder $consulta, Carbon $inicio) => $consulta->where('created_at', '>=', $inicio))
            ->when($request->limiteFinal(), fn (Builder $consulta, Carbon $limite) => $consulta->where('created_at', '<', $limite))
            ->with(StatementEntry::RELACOES)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(self::POR_PAGINA)
            // So os filtros validados seguem nos links: o que mais veio na URL
            // morre nesta pagina.
            ->appends($filtros);

        return view('statement.index', [
            'pagina' => $this->traduzida($pagina, $wallet->id, $user),
            // O saldo ja veio com a carteira; e a leitura que abre o extrato,
            // como no cabecalho de um extrato de banco. Ele e o saldo de hoje,
            // e nao o do periodo: o filtro escolhe o que se le, nao o que se
            // tem.
            'saldo' => Money::fromCents($wallet->balance)->format(),
            'filtros' => $filtros,
            // Quem filtrou e nao achou nada precisa saber que o vazio e do
            // periodo, e nao da carteira.
            'vazio' => $filtros === []
                ? 'Nenhuma movimentação ainda.'
                : 'Nenhuma movimentação nesse período.',
        ]);
    }

    /** Troca os Models pela versao de tela, preservando os links de pagina. */
    private function traduzida(LengthAwarePaginator $pagina, int $walletId, User $user): LengthAwarePaginator
    {
        return $pagina->setCollection(
            $pagina->getCollection()->map(
                fn (WalletEntry $entry) => StatementEntry::from(
                    $entry,
                    $walletId,
                    $this->podeEstornar($entry->transaction, $user),
                )
            )
        );
    }

    /**
     * Se o botao de estorno deve aparecer neste lancamento.
     *
     * A Policy responde por tipo e propriedade; o status entra aqui porque
     * oferecer um botao para uma operacao ja estornada so geraria uma recusa.
     * Nenhuma das duas perguntas custa consulta: a operacao ja veio carregada
     * junto com o lancamento.
     */
    private function podeEstornar(Transaction $transaction, User $user): bool
    {
        return ReversalEligibility::isCompleted($transaction)
            && Gate::forUser($user)->allows('reverse', $transaction);
    }
}
