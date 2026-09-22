<?php

namespace App\Support;

use App\Enums\TransactionType;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * O registro das operacoes financeiras, no mesmo formato para as tres.
 *
 * O identificador da requisicao nao aparece aqui: ele entra sozinho pelo
 * contexto que o middleware publica, e e por isso que as Actions continuam sem
 * conhecer o objeto da requisicao e mesmo assim saem rastreaveis no log.
 */
final class FinancialLog
{
    /**
     * Registra a operacao concluida, e so depois do commit.
     *
     * `DB::afterCommit` resolve os dois casos de uma vez: fora de transacao ele
     * escreve na hora, e dentro de uma transacao maior ele espera o commit dela
     * e desaparece se ela voltar atras. Sem isso, uma operacao desfeita deixaria
     * no log a linha de sucesso de algo que nao aconteceu.
     */
    public static function completed(Transaction $transaction): void
    {
        DB::afterCommit(fn () => Log::info('operação financeira concluída', [
            'transaction_id' => $transaction->id,
            'type' => $transaction->type->value,
            // Repeticao pela chave de idempotencia nao e operacao nova: contar
            // as duas linhas como sucesso dobraria um deposito que aconteceu
            // uma vez so.
            'result' => $transaction->wasRecentlyCreated ? 'completed' : 'repeated',
            ...self::envolvidos($transaction),
        ]));
    }

    /**
     * Registra a recusa prevista sem mudar o rumo dela.
     *
     * Vai como aviso, e nao como erro: a operacao foi recusada pelas regras, e
     * nao por uma falha. O motivo e o nome curto da recusa, para poder ser
     * filtrado; a mensagem de tela nao entra, porque ela muda de texto sem
     * mudar de significado.
     *
     * @param  array<string, int|string|null>  $envolvidos
     */
    public static function refused(TransactionType $type, Throwable $recusa, array $envolvidos = []): void
    {
        Log::warning('operação financeira recusada', [
            'type' => $type->value,
            'result' => 'refused',
            'reason' => Str::snake(class_basename($recusa)),
            ...$envolvidos,
        ]);
    }

    /**
     * Quem participou da operacao: pessoa e carteiras, so identificadores.
     *
     * @return array<string, int|string|null>
     */
    private static function envolvidos(Transaction $transaction): array
    {
        $envolvidos = [
            'initiated_by_user_id' => self::asId($transaction->initiated_by_user_id),
            'source_wallet_id' => self::asId($transaction->source_wallet_id),
            'destination_wallet_id' => self::asId($transaction->destination_wallet_id),
        ];

        if ($transaction->original_transaction_id !== null) {
            $envolvidos['original_transaction_id'] = $transaction->original_transaction_id;
        }

        return $envolvidos;
    }

    /** Normaliza o identificador, que o driver pode devolver como texto. */
    private static function asId(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }
}
