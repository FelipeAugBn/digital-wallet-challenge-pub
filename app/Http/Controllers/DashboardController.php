<?php

namespace App\Http\Controllers;

use App\Models\WalletEntry;
use App\Support\DashboardCharts;
use App\Support\Money;
use App\Support\StatementEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /** Quantas movimentacoes cabem na previa do painel. */
    private const RECENTES = 5;

    /**
     * O painel: saldo, atalhos, graficos e as ultimas movimentacoes.
     *
     * O saldo vem de `wallets.balance`, nunca de uma soma dos lancamentos: o
     * saldo e o valor oficial e o livro-razao e a historia dele. Somar aqui
     * criaria uma segunda verdade, que e justamente o que `wallet:check` existe
     * para comparar mais adiante. Os graficos leem a historia recente, e a
     * curva termina no saldo oficial.
     */
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $wallet = $user->wallet()->firstOrFail();

        $recentes = WalletEntry::query()
            ->where('wallet_id', $wallet->id)
            ->with(StatementEntry::RELACOES)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::RECENTES)
            ->get()
            ->map(fn (WalletEntry $entry) => StatementEntry::from($entry, $wallet->id));

        // A janela dos graficos e so por tempo: a tela promete as cinco semanas
        // e o mes inteiros, entao todo lancamento do periodo entra na conta. A
        // leitura ja vem em ordem cronologica, que e a ordem dos calculos.
        $hoje = Carbon::now();
        $inicio = $hoje->copy()->startOfDay()->subDays(DashboardCharts::DIAS);

        $janela = WalletEntry::query()
            ->where('wallet_id', $wallet->id)
            ->where('created_at', '>=', $inicio)
            ->with('transaction:id,type,status')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        return view('dashboard.index', [
            'nome' => $user->name,
            'saldo' => Money::fromCents($wallet->balance)->format(),
            'recentes' => $recentes,
            'graficos' => $janela->isEmpty() ? null : DashboardCharts::from($janela, $wallet->balance, $inicio, $hoje),
        ]);
    }
}
