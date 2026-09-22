<?php

namespace App\Actions;

use App\Enums\ReversalReason;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\WalletEntryType;
use App\Exceptions\AlreadyReversed;
use App\Exceptions\NotReversible;
use App\Exceptions\ReversalNotAllowed;
use App\Exceptions\TransactionNotFound;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletEntry;
use App\Support\FinancialLog;
use App\Support\Money;
use App\Support\ReversalEligibility;
use App\Support\WalletLocks;
use Illuminate\Support\Facades\DB;

class ReverseTransaction
{
    /**
     * Desfaz a operacao e registra o que aconteceu.
     *
     * Aqui so a fronteira observavel: a regra esta em `reverse`, e o que este
     * metodo acrescenta e o log do resultado. As quatro recusas previstas sao
     * registradas e seguem subindo iguais, tanto para a tela quanto para o
     * comando operacional.
     *
     * @throws TransactionNotFound quando o identificador nao existe
     * @throws NotReversible quando a operacao e um estorno
     * @throws AlreadyReversed quando a operacao ja foi desfeita
     * @throws ReversalNotAllowed quando quem pede nao iniciou a operacao
     */
    public function handle(string $transactionId, ReversalReason $reason, ?User $initiator = null): Transaction
    {
        try {
            $reversal = $this->reverse($transactionId, $reason, $initiator);
        } catch (TransactionNotFound|NotReversible|AlreadyReversed|ReversalNotAllowed $recusa) {
            FinancialLog::refused(TransactionType::Reversal, $recusa, [
                'initiated_by_user_id' => $initiator?->id,
                'original_transaction_id' => $transactionId,
            ]);

            throw $recusa;
        }

        FinancialLog::completed($reversal);

        return $reversal;
    }

    /**
     * Desfaz um deposito ou uma transferencia criando a operacao inversa.
     *
     * Recebe o identificador, nao o Model: o estado que decide o estorno e o
     * status, que muda, e por isso ele so pode ser lido da linha ja bloqueada.
     * Uma instancia carregada antes do lock pode estar velha, e acreditar nela
     * deixaria duas requisicoes simultaneas estornarem a mesma operacao.
     *
     * A ordem e a que a SPEC define: bloquear a original, validar a
     * elegibilidade, bloquear as carteiras por ID crescente e so entao gravar.
     * Nada e apagado e o valor da original nunca muda; o que muda e o status.
     *
     * O motivo e a pessoa iniciadora chegam explicitos porque os dois gatilhos
     * previstos sao diferentes: a solicitacao pela tela tem autor e exige que
     * ele seja quem iniciou a operacao; o estorno por inconsistencia parte de um
     * operador e pode nao ter autor nenhum.
     *
     * @throws TransactionNotFound quando o identificador nao existe
     * @throws NotReversible quando a operacao e um estorno
     * @throws AlreadyReversed quando a operacao ja foi desfeita
     * @throws ReversalNotAllowed quando quem pede nao iniciou a operacao
     */
    private function reverse(string $transactionId, ReversalReason $reason, ?User $initiator): Transaction
    {
        return DB::transaction(function () use ($transactionId, $reason, $initiator): Transaction {
            // Primeira consulta da transacao: a linha original bloqueada. Quem
            // chegar depois espera aqui e so segue com o status atualizado.
            $original = Transaction::query()->whereKey($transactionId)->lockForUpdate()->first()
                ?? throw new TransactionNotFound;

            $this->ensureReversible($original, $reason, $initiator);

            // O dinheiro volta pelo caminho inverso: sai de quem recebeu e
            // entra em quem pagou. No deposito so existe o lado de quem recebeu.
            $debitWalletId = $this->asId($original->destination_wallet_id);
            $creditWalletId = $this->asId($original->source_wallet_id);

            $wallets = WalletLocks::inIdOrder($debitWalletId, $creditWalletId);

            $reversal = Transaction::create([
                'type' => TransactionType::Reversal,
                'status' => TransactionStatus::Completed,
                'amount' => $original->amount,
                'initiated_by_user_id' => $initiator?->id,
                'source_wallet_id' => $debitWalletId,
                'destination_wallet_id' => $creditWalletId,
                'original_transaction_id' => $original->id,
                'reversal_reason' => $reason,
                // O estorno nao usa chave: quem garante uma unica reversao por
                // operacao e a unicidade de `original_transaction_id`.
                'idempotency_key' => null,
            ]);

            $amount = Money::fromCents($original->amount);

            $this->entry($reversal, $wallets[$debitWalletId] ?? null, WalletEntryType::Debit, $amount);
            $this->entry($reversal, $wallets[$creditWalletId] ?? null, WalletEntryType::Credit, $amount);

            // Ultimo passo, ja com tudo gravado: a original fica no lugar, com o
            // mesmo valor, apenas marcada. Se esta escrita falhar, o rollback
            // leva junto o estorno, os lancamentos e os saldos.
            $original->status = TransactionStatus::Reversed;
            $original->save();

            return $reversal;
        });
    }

    /**
     * Confere, com a linha ja bloqueada, se este estorno pode acontecer.
     *
     * As regras vem de `ReversalEligibility`, as mesmas que a Policy e o extrato
     * consultam. A diferenca e o momento: aqui elas valem ate o commit.
     */
    private function ensureReversible(Transaction $original, ReversalReason $reason, ?User $initiator): void
    {
        if (! ReversalEligibility::typeIsReversible($original)) {
            throw new NotReversible;
        }

        if (! ReversalEligibility::isCompleted($original)) {
            throw new AlreadyReversed;
        }

        // O gatilho operacional responde por si; o pedido da pessoa so vale
        // para quem iniciou a operacao, mesmo que a tela ja tenha checado.
        if ($reason === ReversalReason::UserRequest
            && ($initiator === null || ! ReversalEligibility::wasInitiatedBy($original, $initiator))) {
            throw new ReversalNotAllowed;
        }
    }

    /**
     * Grava um lado do estorno e acerta o saldo daquela carteira.
     *
     * O saldo sai da linha bloqueada, entao o `balance_after` corresponde ao
     * saldo confirmado naquele ponto. Aqui o negativo e permitido: a reversao
     * precisa acontecer mesmo que a pessoa ja tenha gastado o valor.
     */
    private function entry(Transaction $reversal, ?Wallet $wallet, WalletEntryType $type, Money $amount): void
    {
        if ($wallet === null) {
            return;
        }

        $balanceAfter = $type === WalletEntryType::Credit
            ? Money::fromCents($wallet->balance)->plus($amount)
            : Money::fromCents($wallet->balance)->minus($amount);

        WalletEntry::create([
            'transaction_id' => $reversal->id,
            'wallet_id' => $wallet->id,
            'type' => $type,
            'amount' => $amount->cents(),
            'balance_after' => $balanceAfter->cents(),
        ]);

        // O saldo fica fora do `$fillable`, entao a atribuicao e direta.
        $wallet->balance = $balanceAfter->cents();
        $wallet->save();
    }

    /** Normaliza o identificador, que o driver pode devolver como texto. */
    private function asId(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }
}
