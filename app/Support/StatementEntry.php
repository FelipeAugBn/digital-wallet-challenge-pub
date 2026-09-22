<?php

namespace App\Support;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\WalletEntryType;
use App\Models\WalletEntry;

/**
 * Um lancamento do livro-razao já traduzido para a tela.
 *
 * O mesmo lancamento conta historias diferentes dependendo de quem olha: a
 * saida de uma transferencia e "enviada" para quem paga e "recebida" para quem
 * recebe. Por isso a carteira de quem esta vendo entra na construcao, e a
 * decisao de rotulo fica aqui, nunca na Blade.
 */
final readonly class StatementEntry
{
    /**
     * O que precisa vir carregado para montar os rotulos sem consulta extra.
     *
     * Fica junto de quem le esses dados, para que a lista nao saia de sincronia
     * com o que o rotulo passa a precisar.
     *
     * @var list<string>
     */
    public const RELACOES = [
        'transaction.sourceWallet.user',
        'transaction.destinationWallet.user',
        'transaction.originalTransaction.sourceWallet.user',
        'transaction.originalTransaction.destinationWallet.user',
    ];

    private function __construct(
        public string $label,
        public string $amount,
        public bool $isCredit,
        public string $date,
        public string $status,
    ) {}

    /** Traduz o lancamento do ponto de vista da carteira que esta na tela. */
    public static function from(WalletEntry $entry, int $walletId): self
    {
        $transaction = $entry->transaction;
        $isCredit = $entry->type === WalletEntryType::Credit;

        // Credito soma, debito subtrai: o sinal vem do proprio valor, e o
        // `Money` formata os dois lados sem duplicar regra de formatacao.
        $signed = Money::fromCents($isCredit ? $entry->amount : -$entry->amount);

        return new self(
            label: self::label($transaction, $walletId, $isCredit),
            amount: $isCredit ? '+'.$signed->format() : $signed->format(),
            isCredit: $isCredit,
            date: $entry->created_at->format('d/m/Y H:i'),
            status: $transaction->status === TransactionStatus::Reversed ? 'Estornada' : 'Concluída',
        );
    }

    /** O rotulo de cada combinacao de operacao e lado do lancamento. */
    private static function label(mixed $transaction, int $walletId, bool $isCredit): string
    {
        return match ($transaction->type) {
            TransactionType::Deposit => 'Depósito',
            TransactionType::Transfer => $isCredit
                ? 'Transferência recebida de '.self::nameOf($transaction->sourceWallet)
                : 'Transferência enviada para '.self::nameOf($transaction->destinationWallet),
            TransactionType::Reversal => self::reversalLabel($transaction->originalTransaction, $walletId),
        };
    }

    /**
     * O estorno se explica pela operacao que ele anula.
     *
     * Os dois lados nomeiam a outra pessoa: quem recebeu precisa entender por
     * que o valor saiu, e quem enviou precisa saber de qual envio o dinheiro
     * esta voltando.
     */
    private static function reversalLabel(mixed $original, int $walletId): string
    {
        if ($original === null) {
            return 'Estorno';
        }

        if ($original->type === TransactionType::Deposit) {
            return 'Estorno de depósito';
        }

        return (int) $original->destination_wallet_id === $walletId
            ? 'Estorno de transferência recebida de '.self::nameOf($original->sourceWallet)
            : 'Estorno de transferência enviada para '.self::nameOf($original->destinationWallet);
    }

    /** O nome de quem esta do outro lado, sem quebrar se o vinculo faltar. */
    private static function nameOf(mixed $wallet): string
    {
        return $wallet?->user?->name ?? 'alguém';
    }
}
