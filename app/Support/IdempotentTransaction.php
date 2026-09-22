<?php

namespace App\Support;

use App\Enums\TransactionType;
use App\Exceptions\IdempotencyConflict;
use App\Models\Transaction;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Executa uma operacao financeira uma unica vez por pessoa e chave.
 *
 * Nao consulta antes se a chave existe: quem decide entre operacao nova e
 * repeticao e a propria tentativa de gravar, porque so o banco enxerga duas
 * requisicoes simultaneas. Se o indice de idempotencia recusar a insercao, a
 * transacao ja voltou atras e a operacao original e lida de novo.
 */
final class IdempotentTransaction
{
    /** O indice parcial criado na migration de `transactions`. */
    private const INDICE = 'transactions_user_idempotency_key_unique';

    /** O codigo do PostgreSQL para violacao de unicidade. */
    private const UNIQUE_VIOLATION = '23505';

    /**
     * Roda a operacao e devolve a transacao gravada, nova ou ja existente.
     *
     * @param  Closure(): Transaction  $operation
     *
     * @throws IdempotencyConflict quando a chave voltou com outros dados
     */
    public function run(
        int $initiatedByUserId,
        string $idempotencyKey,
        TransactionType $type,
        Money $amount,
        ?int $destinationWalletId,
        Closure $operation,
    ): Transaction {
        try {
            return DB::transaction($operation);
        } catch (QueryException $exception) {
            if (! $this->isIdempotencyViolation($exception)) {
                throw $exception;
            }

            // A esta altura o `DB::transaction` ja desfez tudo, entao a consulta
            // abaixo roda numa operacao nova e enxerga o que foi gravado antes.
            $persisted = Transaction::query()
                ->where('initiated_by_user_id', $initiatedByUserId)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($persisted === null) {
                throw $exception;
            }

            return $this->sameOperation($persisted, $type, $amount, $destinationWalletId);
        }
    }

    /**
     * So o indice de idempotencia significa repeticao.
     *
     * O nome vai entre aspas porque e assim que o PostgreSQL escreve na
     * mensagem, e isso impede que um indice de nome parecido seja confundido.
     */
    private function isIdempotencyViolation(QueryException $exception): bool
    {
        return $exception->getCode() === self::UNIQUE_VIOLATION
            && str_contains($exception->getMessage(), '"'.self::INDICE.'"');
    }

    /** Compara o que foi pedido com o que ja existe antes de chamar de repeticao. */
    private function sameOperation(
        Transaction $persisted,
        TransactionType $type,
        Money $amount,
        ?int $destinationWalletId,
    ): Transaction {
        $igual = $persisted->type === $type
            && $persisted->amount === $amount->cents()
            && $this->asId($persisted->destination_wallet_id) === $destinationWalletId;

        if (! $igual) {
            throw new IdempotencyConflict;
        }

        return $persisted;
    }

    /** Normaliza o identificador, que o driver pode devolver como texto. */
    private function asId(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }
}
