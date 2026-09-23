<?php

namespace App\Support;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\WalletEntryType;
use App\Models\WalletEntry;
use Illuminate\Support\Carbon;

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

    /**
     * Os meses por extenso.
     *
     * Ficam explicitos, em vez de sair das traducoes do Carbon, porque o texto
     * do cabecalho e parte do que a tela promete e os testes fixam: assim ele
     * nao muda com uma versao da biblioteca nem com o idioma em tempo de
     * execucao. O painel reaproveita a lista nos graficos.
     *
     * @var array<int, string>
     */
    public const MESES = [
        1 => 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho',
        'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro',
    ];

    private function __construct(
        public string $label,
        public string $amount,
        public bool $isCredit,
        public string $dayKey,
        public string $dayLabel,
        public string $time,
        public string $status,
        public ?string $reversibleId,
    ) {}

    /**
     * Traduz o lancamento do ponto de vista da carteira que esta na tela.
     *
     * `$canReverse` chega pronto de quem sabe responder: a tela nao pergunta
     * quem e a pessoa nem consulta Policy. Quando o estorno nao cabe, o
     * identificador nem aparece na pagina.
     */
    public static function from(WalletEntry $entry, int $walletId, bool $canReverse = false): self
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
            // O dia e a hora saem separados porque a tela agrupa por dia: a
            // data inteira em toda linha repetiria o mesmo texto varias vezes.
            dayKey: $entry->created_at->format('Y-m-d'),
            dayLabel: self::dayLabel($entry->created_at),
            time: $entry->created_at->format('H:i'),
            status: $transaction->status === TransactionStatus::Reversed ? 'Estornada' : 'Concluída',
            reversibleId: $canReverse ? $transaction->id : null,
        );
    }

    /**
     * O dia como alguem diria em voz alta.
     *
     * Os dois dias mais recentes ganham nome, que e como se fala deles; o resto
     * vira data por extenso, e o ano so aparece quando nao e o corrente.
     */
    private static function dayLabel(Carbon $momento): string
    {
        $hoje = Carbon::now($momento->getTimezone())->startOfDay();
        $dia = $momento->copy()->startOfDay();

        if ($dia->equalTo($hoje)) {
            return 'Hoje';
        }

        if ($dia->equalTo($hoje->copy()->subDay())) {
            return 'Ontem';
        }

        $rotulo = $momento->day.' de '.self::MESES[$momento->month];

        return $momento->year === $hoje->year ? $rotulo : $rotulo.' de '.$momento->year;
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
