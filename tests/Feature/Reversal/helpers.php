<?php

use App\Actions\ReverseTransaction;
use App\Enums\ReversalReason;
use App\Models\Transaction;
use App\Models\User;
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

/** O botão de estorno oferecido para esta operação, se estiver na tela. */
function temBotaoDeEstorno(string $html, Transaction $transaction): bool
{
    return str_contains($html, route('reversals.store', $transaction->id));
}
