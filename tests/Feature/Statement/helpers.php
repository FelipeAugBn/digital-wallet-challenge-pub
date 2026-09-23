<?php

use App\Enums\ReversalReason;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\WalletEntryType;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletEntry;
use App\Support\Money;
use Illuminate\Support\Carbon;

// Reaproveita `userWithWallet`, `walletOf`, `deposit` e `transfer`, que servem
// a qualquer tela financeira; o extrato só precisa acrescentar o estorno.
require_once __DIR__.'/../Transfer/helpers.php';

/**
 * Monta um estorno direto no banco, sem a Action da T010.
 *
 * O extrato precisa saber desenhar um estorno antes de existir uma forma de
 * criá-lo pela aplicação. O que importa aqui é a forma do dado: transação de
 * estorno ligada à original, lançamentos invertidos e a original marcada.
 */
function reverseDirectly(Transaction $original): Transaction
{
    $estorno = Transaction::create([
        'type' => TransactionType::Reversal,
        'status' => TransactionStatus::Completed,
        'amount' => $original->amount,
        'initiated_by_user_id' => $original->initiated_by_user_id,
        'source_wallet_id' => $original->destination_wallet_id,
        'destination_wallet_id' => $original->source_wallet_id,
        'original_transaction_id' => $original->id,
        'reversal_reason' => ReversalReason::UserRequest,
    ]);

    // O valor sai de quem recebeu e volta para quem mandou; no depósito só
    // existe o lado de quem recebeu.
    reverseEntry($estorno, $original->destination_wallet_id, WalletEntryType::Debit);
    reverseEntry($estorno, $original->source_wallet_id, WalletEntryType::Credit);

    $original->update(['status' => TransactionStatus::Reversed]);

    return $estorno;
}

/** Grava um lado do estorno e acerta o saldo da carteira correspondente. */
function reverseEntry(Transaction $estorno, ?int $walletId, WalletEntryType $type): void
{
    if ($walletId === null) {
        return;
    }

    $wallet = Wallet::query()->whereKey($walletId)->sole();
    $saldo = $type === WalletEntryType::Credit
        ? $wallet->balance + $estorno->amount
        : $wallet->balance - $estorno->amount;

    WalletEntry::create([
        'transaction_id' => $estorno->id,
        'wallet_id' => $walletId,
        'type' => $type,
        'amount' => $estorno->amount,
        'balance_after' => $saldo,
    ]);

    Wallet::query()->whereKey($walletId)->update(['balance' => $saldo]);
}

/**
 * Faz `$quantidade` depósitos de 1, 2, 3… centavos.
 *
 * Valores distintos e crescentes dão um rótulo único a cada lançamento, o que
 * permite provar que a paginação não repete nem omite nenhum deles.
 */
function depositosCrescentes(User $user, int $quantidade, int $primeiro = 1): void
{
    for ($centavos = $primeiro; $centavos < $primeiro + $quantidade; $centavos++) {
        deposit($user, centavosComoTexto($centavos));
    }
}

/** Escreve um valor em centavos no formato que o formulário aceita. */
function centavosComoTexto(int $centavos): string
{
    return intdiv($centavos, 100).','.str_pad((string) ($centavos % 100), 2, '0', STR_PAD_LEFT);
}

/** O mesmo valor, já no formato de crédito que aparece na tela. */
function creditoNaTela(int $centavos): string
{
    return '+'.Money::fromCents($centavos)->format();
}

/** Os valores que aparecem na tela, na ordem em que aparecem. */
function valoresNaTela(string $html): array
{
    preg_match_all('/<span class="valor[^"]*">([^<]+)<\/span>/', $html, $achados);

    return array_map('trim', $achados[1]);
}

/** Os cabeçalhos de dia que aparecem na tela, na ordem em que aparecem. */
function diasNaTela(string $html): array
{
    preg_match_all('/<li class="dia">([^<]+)<\/li>/', $html, $achados);

    return array_map('trim', $achados[1]);
}

/**
 * Um depósito feito no instante dado, como se a pessoa o tivesse feito naquela hora.
 *
 * O relógio fica onde foi posto: quem chama devolve com `Carbon::setTestNow()`.
 */
function depositoEm(string $quando, User $pessoa, string $valor = '1,00'): void
{
    Carbon::setTestNow($quando);
    deposit($pessoa, $valor);
}

/** Força o mesmo instante em todos os lançamentos, para testar o desempate. */
function mesmoInstante(): void
{
    WalletEntry::query()->update(['created_at' => '2026-03-10 12:00:00']);
}
