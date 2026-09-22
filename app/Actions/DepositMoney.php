<?php

namespace App\Actions;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\WalletEntryType;
use App\Exceptions\IdempotencyConflict;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletEntry;
use App\Support\FinancialLog;
use App\Support\IdempotentTransaction;
use App\Support\Money;

class DepositMoney
{
    /** O componente vem pelo construtor, como nas demais Actions. */
    public function __construct(private readonly IdempotentTransaction $idempotentTransaction) {}

    /**
     * Credita a carteira da pessoa e registra o que aconteceu.
     *
     * Aqui so a fronteira observavel: a regra esta em `credit`, e o que este
     * metodo acrescenta e o log do resultado. A recusa prevista e registrada e
     * segue subindo igual, para que quem chamou continue decidindo por ela.
     */
    public function handle(User $user, Money $amount, string $idempotencyKey): Transaction
    {
        try {
            $transaction = $this->credit($user, $amount, $idempotencyKey);
        } catch (IdempotencyConflict $recusa) {
            FinancialLog::refused(TransactionType::Deposit, $recusa, [
                'initiated_by_user_id' => $user->id,
            ]);

            throw $recusa;
        }

        FinancialLog::completed($transaction);

        return $transaction;
    }

    /**
     * Credita a propria carteira da pessoa, uma vez so por chave.
     *
     * A ordem dentro da transacao e deliberada: a insercao em `transactions`
     * vem antes do lock para que uma requisicao repetida seja recusada pelo
     * indice sem antes segurar a carteira. O lock so e liberado no commit.
     */
    private function credit(User $user, Money $amount, string $idempotencyKey): Transaction
    {
        // Fora da transacao ainda, e so o identificador: o saldo precisa ser
        // lido depois do lock, nunca de uma instancia carregada antes dele.
        $walletId = (int) $user->wallet()->value('id');

        return $this->idempotentTransaction->run(
            $user->id,
            $idempotencyKey,
            TransactionType::Deposit,
            $amount,
            $walletId,
            function () use ($user, $walletId, $amount, $idempotencyKey): Transaction {
                $transaction = Transaction::create([
                    'type' => TransactionType::Deposit,
                    'status' => TransactionStatus::Completed,
                    'amount' => $amount->cents(),
                    'initiated_by_user_id' => $user->id,
                    'source_wallet_id' => null,
                    'destination_wallet_id' => $walletId,
                    'idempotency_key' => $idempotencyKey,
                ]);

                $wallet = Wallet::query()->whereKey($walletId)->lockForUpdate()->firstOrFail();
                $balanceAfter = Money::fromCents($wallet->balance)->plus($amount);

                WalletEntry::create([
                    'transaction_id' => $transaction->id,
                    'wallet_id' => $walletId,
                    'type' => WalletEntryType::Credit,
                    'amount' => $amount->cents(),
                    'balance_after' => $balanceAfter->cents(),
                ]);

                // O saldo fica fora do `$fillable`, entao a atribuicao e direta.
                $wallet->balance = $balanceAfter->cents();
                $wallet->save();

                return $transaction;
            },
        );
    }
}
