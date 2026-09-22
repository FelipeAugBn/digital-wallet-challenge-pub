<?php

namespace App\Http\Controllers;

use App\Models\WalletEntry;
use App\Support\StatementEntry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
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
     */
    public function __invoke(Request $request): View
    {
        $wallet = $request->user()->wallet()->firstOrFail();

        $pagina = WalletEntry::query()
            ->where('wallet_id', $wallet->id)
            ->with(StatementEntry::RELACOES)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(self::POR_PAGINA);

        return view('statement.index', [
            'pagina' => $this->traduzida($pagina, $wallet->id),
        ]);
    }

    /** Troca os Models pela versao de tela, preservando os links de pagina. */
    private function traduzida(LengthAwarePaginator $pagina, int $walletId): LengthAwarePaginator
    {
        return $pagina->setCollection(
            $pagina->getCollection()->map(
                fn (WalletEntry $entry) => StatementEntry::from($entry, $walletId)
            )
        );
    }
}
