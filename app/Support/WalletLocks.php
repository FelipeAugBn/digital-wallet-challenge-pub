<?php

namespace App\Support;

use App\Models\Wallet;

/**
 * A ordem global de locks de carteira, num lugar so.
 *
 * A SPEC manda bloquear sempre do menor ID para o maior, para que duas
 * operacoes que toquem as mesmas duas carteiras nunca fiquem esperando uma pela
 * outra. Transferencia e estorno seguem a mesma regra, entao ela vive aqui em
 * vez de ser reescrita em cada Action.
 */
final class WalletLocks
{
    /**
     * Bloqueia as carteiras informadas, do menor ID para o maior.
     *
     * Sao consultas separadas de proposito: assim a ordem dos locks e a ordem
     * em que o PostgreSQL recebe os comandos, e nao uma escolha do planejador
     * dentro de um `where in` com `order by`. Identificadores nulos e repetidos
     * sao descartados, porque deposito e estorno de deposito tem um lado so.
     *
     * @return array<int, Wallet> indexado pelo ID da carteira
     */
    public static function inIdOrder(?int ...$walletIds): array
    {
        $ids = array_values(array_unique(array_filter(
            $walletIds,
            fn (?int $id) => $id !== null,
        )));

        sort($ids);

        $wallets = [];

        foreach ($ids as $id) {
            $wallets[$id] = Wallet::query()->whereKey($id)->lockForUpdate()->firstOrFail();
        }

        return $wallets;
    }
}
