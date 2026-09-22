<?php

namespace App\Actions;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\WalletEntryType;
use App\Exceptions\InsufficientFunds;
use App\Exceptions\RecipientNotFound;
use App\Exceptions\TransferToSelf;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletEntry;
use App\Support\IdempotentTransaction;
use App\Support\Money;

class TransferMoney
{
    /** O mesmo componente do deposito, sem copia de regra de idempotencia. */
    public function __construct(private readonly IdempotentTransaction $idempotentTransaction) {}

    /**
     * Move dinheiro de uma carteira para outra, uma vez so por chave.
     *
     * A ordem dentro da transacao repete a do deposito: a insercao em
     * `transactions` vem antes de qualquer lock, para que uma repeticao seja
     * recusada pelo indice sem antes segurar carteira nenhuma. Depois disso as
     * duas carteiras sao bloqueadas por ID crescente, sempre na mesma ordem,
     * para que duas transferencias cruzadas nao fiquem esperando uma pela
     * outra. Os locks so caem no commit.
     *
     * @throws RecipientNotFound quando o e-mail nao pertence a ninguem
     * @throws TransferToSelf quando o e-mail e o de quem esta enviando
     * @throws InsufficientFunds quando o saldo bloqueado nao cobre o valor
     */
    public function handle(User $sender, string $recipientEmail, Money $amount, string $idempotencyKey): Transaction
    {
        $recipient = User::query()->where('email', $recipientEmail)->first()
            ?? throw new RecipientNotFound;

        // Antes dos locks: a mesma carteira bloqueada duas vezes nao moveria
        // dinheiro nenhum, e o destino tem de ser outra pessoa de verdade.
        if ($recipient->id === $sender->id) {
            throw new TransferToSelf;
        }

        // Aqui so os identificadores. Os saldos sao lidos depois do lock,
        // nunca de uma instancia carregada antes dele.
        $sourceWalletId = (int) $sender->wallet()->value('id');
        $destinationWalletId = (int) $recipient->wallet()->value('id');

        return $this->idempotentTransaction->run(
            $sender->id,
            $idempotencyKey,
            TransactionType::Transfer,
            $amount,
            $destinationWalletId,
            function () use ($sender, $sourceWalletId, $destinationWalletId, $amount, $idempotencyKey): Transaction {
                $transaction = Transaction::create([
                    'type' => TransactionType::Transfer,
                    'status' => TransactionStatus::Completed,
                    'amount' => $amount->cents(),
                    'initiated_by_user_id' => $sender->id,
                    'source_wallet_id' => $sourceWalletId,
                    'destination_wallet_id' => $destinationWalletId,
                    'idempotency_key' => $idempotencyKey,
                ]);

                $wallets = $this->lockInIdOrder($sourceWalletId, $destinationWalletId);

                $source = $wallets[$sourceWalletId];
                $destination = $wallets[$destinationWalletId];

                // O saldo vem da linha ja bloqueada, entao esta conferencia
                // continua valendo ate o commit.
                $sourceAfter = Money::fromCents($source->balance)->minus($amount);

                if ($sourceAfter->cents() < 0) {
                    throw new InsufficientFunds;
                }

                $destinationAfter = Money::fromCents($destination->balance)->plus($amount);

                // Debito primeiro, credito depois, os dois no mesmo commit:
                // nunca pode sobrar um sem o outro.
                WalletEntry::create([
                    'transaction_id' => $transaction->id,
                    'wallet_id' => $sourceWalletId,
                    'type' => WalletEntryType::Debit,
                    'amount' => $amount->cents(),
                    'balance_after' => $sourceAfter->cents(),
                ]);

                WalletEntry::create([
                    'transaction_id' => $transaction->id,
                    'wallet_id' => $destinationWalletId,
                    'type' => WalletEntryType::Credit,
                    'amount' => $amount->cents(),
                    'balance_after' => $destinationAfter->cents(),
                ]);

                // O saldo fica fora do `$fillable`, entao a atribuicao e direta.
                $source->balance = $sourceAfter->cents();
                $source->save();

                $destination->balance = $destinationAfter->cents();
                $destination->save();

                return $transaction;
            },
        );
    }

    /**
     * Bloqueia as duas carteiras do menor ID para o maior.
     *
     * Sao duas consultas separadas de proposito: assim a ordem dos locks e a
     * ordem em que o PostgreSQL recebe os comandos, e nao uma escolha do
     * planejador dentro de um `where in` com `order by`.
     *
     * @return array<int, Wallet> indexado pelo ID da carteira
     */
    private function lockInIdOrder(int $sourceWalletId, int $destinationWalletId): array
    {
        $ids = [$sourceWalletId, $destinationWalletId];
        sort($ids);

        $wallets = [];

        foreach ($ids as $id) {
            $wallets[$id] = Wallet::query()->whereKey($id)->lockForUpdate()->firstOrFail();
        }

        return $wallets;
    }
}
