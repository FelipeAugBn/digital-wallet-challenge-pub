<?php

use App\Actions\ReverseTransaction;
use App\Enums\ReversalReason;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletEntry;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

// Reaproveita `userWithWallet`, `walletOf`, `deposit`, `transfer` e os
// auxiliares de leitura de tela; o estorno só acrescenta o que é dele.
require_once __DIR__.'/../Statement/helpers.php';

/**
 * Chama a Action real pelo container, como o controller faz.
 *
 * Quem pede vai sempre explícito, inclusive quando é `null`: o motivo e a
 * pessoa iniciadora fazem parte do que está sendo testado aqui.
 */
function reverse(
    Transaction $original,
    ?User $initiator,
    ReversalReason $reason = ReversalReason::UserRequest,
): Transaction {
    return app(ReverseTransaction::class)->handle($original->id, $reason, $initiator);
}

/**
 * Roda o trecho ouvindo o banco e devolve as consultas na ordem real.
 *
 * É assim que a ordem dos locks é conferida: sem relógio, sem paralelismo e sem
 * depender de quanto tempo alguma coisa demora.
 *
 * @return list<array{sql: string, bindings: array<int, mixed>}>
 */
function consultasDe(Closure $trecho): array
{
    $consultas = [];

    DB::listen(function ($query) use (&$consultas) {
        $consultas[] = ['sql' => $query->sql, 'bindings' => $query->bindings];
    });

    $trecho();

    return $consultas;
}

/** As posições das consultas cujo SQL contém todos os trechos informados. */
function posicoesCom(array $consultas, string ...$trechos): array
{
    $posicoes = [];

    foreach ($consultas as $posicao => $consulta) {
        $casa = true;

        foreach ($trechos as $trecho) {
            $casa = $casa && str_contains($consulta['sql'], $trecho);
        }

        if ($casa) {
            $posicoes[] = $posicao;
        }
    }

    return $posicoes;
}

/**
 * Roda o comando operacional de verdade e devolve o que ele respondeu.
 *
 * Nada de simular a chamada: o que precisa ser provado é o caminho inteiro,
 * da linha de comando até o banco.
 *
 * @param  array<string, mixed>  $parametros
 * @return array{codigo: int, saida: string}
 */
function estornoOperacional(array $parametros): array
{
    $codigo = Artisan::call('wallet:reverse', $parametros);

    return ['codigo' => $codigo, 'saida' => Artisan::output()];
}

/** Os mesmos parâmetros, já no formato que o comando espera. */
function parametrosDoComando(string $transactionId, ?string $reason = 'inconsistency'): array
{
    $parametros = ['transaction' => $transactionId];

    if ($reason !== null) {
        $parametros['--reason'] = $reason;
    }

    return $parametros;
}

/** Uma fotografia do que não pode mudar quando o comando recusa. */
function retratoFinanceiro(): array
{
    return [
        'transacoes' => Transaction::query()->orderBy('id')->get(['id', 'status', 'reversal_reason'])->toArray(),
        'lancamentos' => WalletEntry::query()->orderBy('id')->get(['id', 'wallet_id', 'type', 'amount', 'balance_after'])->toArray(),
        'saldos' => Wallet::query()->orderBy('id')->pluck('balance', 'id')->toArray(),
    ];
}

/** O botão de estorno oferecido para esta operação, se estiver na tela. */
function temBotaoDeEstorno(string $html, Transaction $transaction): bool
{
    return str_contains($html, route('reversals.store', $transaction->id));
}
